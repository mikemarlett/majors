<?php

declare(strict_types=1);

namespace Majors\DegreeMaps;

use mysqli;
use RuntimeException;

/**
 * Write side of the degree_maps* tables: header, courses, footnotes, hours,
 * delete, clone. Every method takes already-validated scalars; permission
 * checks happen in the ajax actions before anything here is called.
 */
final class MapEditor
{
    public function __construct(private readonly mysqli $db)
    {
    }

    // ---- header -------------------------------------------------------------

    /**
     * @param array{major:string,degree_type:string,college:string,department:?string,note:?string,
     *              academic_year:int,program_id:?int,hours_to_graduate?:?string} $d
     * @return int map id
     */
    public function saveHeader(array $d, ?int $id = null): int
    {
        $programId = !empty($d['program_id']) ? (int) $d['program_id'] : null;
        $dept      = $d['department'] !== null && $d['department'] !== '' ? $d['department'] : null;
        $note      = $d['note'] !== null && $d['note'] !== '' ? $d['note'] : null;
        if ($id === null) {
            $htg  = isset($d['hours_to_graduate']) && $d['hours_to_graduate'] !== '' ? (string) $d['hours_to_graduate'] : '120';
            $stmt = $this->db->prepare(
                'INSERT INTO `degree_maps` (`major`,`degree_type`,`college`,`department`,`note`,`academic_year`,`program_id`,`hours_to_graduate`,`timestamp`)
                 VALUES (?,?,?,?,?,?,?,?,NOW())'
            );
            $stmt->bind_param('sssssiis', $d['major'], $d['degree_type'], $d['college'], $dept, $note, $d['academic_year'], $programId, $htg);
            $stmt->execute();
            $id = (int) $this->db->insert_id;
            $stmt->close();
            return $id;
        }
        $stmt = $this->db->prepare(
            'UPDATE `degree_maps` SET `major`=?, `degree_type`=?, `college`=?, `department`=?, `note`=?, `academic_year`=?, `program_id`=?, `timestamp`=NOW()
              WHERE `id`=?'
        );
        $stmt->bind_param('sssssiii', $d['major'], $d['degree_type'], $d['college'], $dept, $note, $d['academic_year'], $programId, $id);
        $stmt->execute();
        $stmt->close();
        return $id;
    }

    public function delete(int $id): void
    {
        $this->db->begin_transaction();
        try {
            foreach (['degree_maps_courses', 'degree_maps_footnotes', 'degree_maps_semester_hours', 'degree_maps_year_hours'] as $table) {
                $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE `degree_map_id` = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
            $stmt = $this->db->prepare('DELETE FROM `degree_maps` WHERE `id` = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // ---- courses ------------------------------------------------------------

    /**
     * @param array{id:?int,degree_map_id:int,course_info:string,hours:string,year:int,semester:int,order:?int,
     *              footnote_ids:?list<int>,sge:?string,extra:?string,scbcrse_subj_code:?string,scbcrse_crse_numb:?string} $c
     * @return int course id
     */
    public function saveCourse(array $c): int
    {
        $fids  = !empty($c['footnote_ids']) ? json_encode(array_values(array_map('intval', $c['footnote_ids']))) : null;
        $sge   = !empty($c['sge']) ? (string) $c['sge'] : null;
        $extra = isset($c['extra']) && trim((string) $c['extra']) !== '' && $c['extra'] !== '0' ? trim((string) $c['extra']) : null;
        $subj  = !empty($c['scbcrse_subj_code']) ? (string) $c['scbcrse_subj_code'] : null;
        $numb  = !empty($c['scbcrse_crse_numb']) ? (string) $c['scbcrse_crse_numb'] : null;
        $order = !empty($c['order']) ? (int) $c['order'] : $this->nextOrder($c['degree_map_id'], $c['year'], $c['semester']);

        if (!empty($c['id'])) {
            $id   = (int) $c['id'];
            $stmt = $this->db->prepare(
                'UPDATE `degree_maps_courses`
                    SET `course_info`=?, `hours`=?, `year`=?, `semester`=?, `order`=?, `footnote_ids`=?, `sge`=?, `extra`=?,
                        `scbcrse_subj_code`=?, `scbcrse_crse_numb`=?, `timestamp`=NOW()
                  WHERE `id`=? AND `degree_map_id`=?'
            );
            $stmt->bind_param('ssiiisssssii', $c['course_info'], $c['hours'], $c['year'], $c['semester'], $order, $fids, $sge, $extra, $subj, $numb, $id, $c['degree_map_id']);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO `degree_maps_courses`
                    (`degree_map_id`,`course_info`,`hours`,`year`,`semester`,`order`,`footnote_ids`,`sge`,`extra`,`scbcrse_subj_code`,`scbcrse_crse_numb`,`timestamp`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())'
            );
            $stmt->bind_param('issiiisssss', $c['degree_map_id'], $c['course_info'], $c['hours'], $c['year'], $c['semester'], $order, $fids, $sge, $extra, $subj, $numb);
            $stmt->execute();
            $id = (int) $this->db->insert_id;
            $stmt->close();
        }
        $this->reorderSemester($c['degree_map_id'], $c['year'], $c['semester']);
        $this->touch($c['degree_map_id']);
        return $id;
    }

    public function deleteCourse(int $courseId, int $mapId): bool
    {
        $stmt = $this->db->prepare('SELECT `year`, `semester` FROM `degree_maps_courses` WHERE `id` = ? AND `degree_map_id` = ?');
        $stmt->bind_param('ii', $courseId, $mapId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return false;
        }
        $stmt = $this->db->prepare('DELETE FROM `degree_maps_courses` WHERE `id` = ? AND `degree_map_id` = ?');
        $stmt->bind_param('ii', $courseId, $mapId);
        $stmt->execute();
        $stmt->close();
        $this->reorderSemester($mapId, (int) $row['year'], (int) $row['semester']);
        $this->touch($mapId);
        return true;
    }

    /**
     * Drag-and-drop result: new (order, year, semester) for each course id.
     * @param list<array{id:int,order:int,year:int,semester:int}> $items
     */
    public function saveCourseOrder(int $mapId, array $items): void
    {
        $stmt = $this->db->prepare('UPDATE `degree_maps_courses` SET `order` = ?, `year` = ?, `semester` = ? WHERE `id` = ? AND `degree_map_id` = ?');
        $this->db->begin_transaction();
        try {
            foreach ($items as $i) {
                $stmt->bind_param('iiiii', $i['order'], $i['year'], $i['semester'], $i['id'], $mapId);
                $stmt->execute();
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        } finally {
            $stmt->close();
        }
        $this->touch($mapId);
    }

    public function reorderSemester(int $mapId, int $year, int $semester): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `degree_maps_courses` AS d
               JOIN (SELECT `id`, @rn := @rn + 1 AS `new_order`
                       FROM `degree_maps_courses`, (SELECT @rn := 0) r
                      WHERE `degree_map_id` = ? AND `year` = ? AND `semester` = ?
                      ORDER BY `order` ASC, `id` ASC) AS t ON d.`id` = t.`id`
                SET d.`order` = t.`new_order`'
        );
        $stmt->bind_param('iii', $mapId, $year, $semester);
        $stmt->execute();
        $stmt->close();
    }

    private function nextOrder(int $mapId, int $year, int $semester): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(`order`), 0) + 1 FROM `degree_maps_courses` WHERE `degree_map_id` = ? AND `year` = ? AND `semester` = ?');
        $stmt->bind_param('iii', $mapId, $year, $semester);
        $stmt->execute();
        $n = (int) $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $n;
    }

    // ---- hours ----------------------------------------------------------------

    /**
     * @param array<int,array<int|string,mixed>> $hours [year][1|2|3 => hours, 'total_hours' => hours]
     */
    public function saveHours(int $mapId, array $hours, string $hoursToGraduate): void
    {
        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare('UPDATE `degree_maps` SET `hours_to_graduate` = ?, `timestamp` = NOW() WHERE `id` = ?');
            $stmt->bind_param('si', $hoursToGraduate, $mapId);
            $stmt->execute();
            $stmt->close();

            $delSem = $this->db->prepare('DELETE FROM `degree_maps_semester_hours` WHERE `degree_map_id` = ? AND `year` = ? AND `semester` = ?');
            $putSem = $this->db->prepare('REPLACE INTO `degree_maps_semester_hours` (`degree_map_id`,`year`,`semester`,`hours`,`timestamp`) VALUES (?,?,?,?,NOW())');
            $delYr  = $this->db->prepare('DELETE FROM `degree_maps_year_hours` WHERE `degree_map_id` = ? AND `year` = ?');
            $putYr  = $this->db->prepare('REPLACE INTO `degree_maps_year_hours` (`degree_map_id`,`year`,`hours`,`timestamp`) VALUES (?,?,?,NOW())');

            foreach ($hours as $year => $data) {
                $year = (int) $year;
                if ($year < 1 || $year > 4 || !is_array($data)) {
                    continue;
                }
                foreach ([1, 2, 3] as $s) {
                    if (!array_key_exists($s, $data)) {
                        continue;
                    }
                    $v = trim((string) $data[$s]);
                    if ($v === '') {
                        $delSem->bind_param('iii', $mapId, $year, $s);
                        $delSem->execute();
                    } else {
                        $putSem->bind_param('iiis', $mapId, $year, $s, $v);
                        $putSem->execute();
                    }
                }
                if (array_key_exists('total_hours', $data)) {
                    $v = trim((string) $data['total_hours']);
                    if ($v === '') {
                        $delYr->bind_param('ii', $mapId, $year);
                        $delYr->execute();
                    } else {
                        $putYr->bind_param('iis', $mapId, $year, $v);
                        $putYr->execute();
                    }
                }
            }
            foreach ([$delSem, $putSem, $delYr, $putYr] as $st) {
                $st->close();
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // ---- footnotes -----------------------------------------------------------

    /**
     * Save the footnote list in the order given; ids starting with "new_" are inserted.
     * @param list<array{id:string|int,note:string}> $footnotes
     */
    public function saveFootnotes(int $mapId, array $footnotes): void
    {
        $this->db->begin_transaction();
        try {
            $ins = $this->db->prepare('INSERT INTO `degree_maps_footnotes` (`degree_map_id`,`order`,`note`,`timestamp`) VALUES (?,?,?,NOW())');
            $upd = $this->db->prepare('UPDATE `degree_maps_footnotes` SET `order` = ?, `note` = ? WHERE `id` = ? AND `degree_map_id` = ?');
            $order = 0;
            foreach ($footnotes as $f) {
                $order++;
                $note = trim((string) ($f['note'] ?? ''));
                if (str_starts_with((string) $f['id'], 'new_')) {
                    if ($note === '') {
                        $order--;
                        continue;
                    }
                    $ins->bind_param('iis', $mapId, $order, $note);
                    $ins->execute();
                } else {
                    $fid = (int) $f['id'];
                    $upd->bind_param('isii', $order, $note, $fid, $mapId);
                    $upd->execute();
                }
            }
            $ins->close();
            $upd->close();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        $this->reorderFootnotes($mapId);
        $this->touch($mapId);
    }

    public function deleteFootnote(int $footnoteId, int $mapId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM `degree_maps_footnotes` WHERE `id` = ? AND `degree_map_id` = ?');
        $stmt->bind_param('ii', $footnoteId, $mapId);
        $stmt->execute();
        $ok = $stmt->affected_rows === 1;
        $stmt->close();
        if ($ok) {
            $this->detachFootnoteFromCourses($footnoteId, $mapId);
            $this->reorderFootnotes($mapId);
            $this->touch($mapId);
        }
        return $ok;
    }

    public function reorderFootnotes(int $mapId): void
    {
        // Order 0 is reserved for the un-numbered "Note:" footnote; renumber the rest 1..n.
        $stmt = $this->db->prepare(
            'UPDATE `degree_maps_footnotes` AS d
               JOIN (SELECT `id`, @rn := @rn + 1 AS `new_order`
                       FROM `degree_maps_footnotes`, (SELECT @rn := 0) r
                      WHERE `degree_map_id` = ? AND `order` <> 0
                      ORDER BY `order` ASC, `id` ASC) AS t ON d.`id` = t.`id`
                SET d.`order` = t.`new_order`'
        );
        $stmt->bind_param('i', $mapId);
        $stmt->execute();
        $stmt->close();
    }

    /** Remove a deleted footnote's id from every course's footnote_ids JSON. */
    private function detachFootnoteFromCourses(int $footnoteId, int $mapId): void
    {
        $stmt = $this->db->prepare('SELECT `id`, `footnote_ids` FROM `degree_maps_courses` WHERE `degree_map_id` = ? AND `footnote_ids` LIKE ?');
        $like = '%' . $footnoteId . '%';
        $stmt->bind_param('is', $mapId, $like);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $upd = $this->db->prepare('UPDATE `degree_maps_courses` SET `footnote_ids` = ? WHERE `id` = ?');
        foreach ($rows as $r) {
            $ids = json_decode((string) $r['footnote_ids'], true);
            if (!is_array($ids)) {
                continue;
            }
            $kept = array_values(array_filter(array_map('intval', $ids), static fn (int $i) => $i !== $footnoteId));
            $json = $kept === [] ? null : json_encode($kept);
            $cid  = (int) $r['id'];
            $upd->bind_param('si', $json, $cid);
            $upd->execute();
        }
        $upd->close();
    }

    // ---- clone ----------------------------------------------------------------

    /** Copy a map (header, footnotes, courses with remapped footnote ids, hours) into $newYear. Returns the new id. */
    public function clone(int $sourceId, int $newYear): int
    {
        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO `degree_maps` (`program_id`,`major`,`college`,`degree_type`,`department`,`note`,`academic_year`,`hours_to_graduate`,`timestamp`)
                 SELECT `program_id`,`major`,`college`,`degree_type`,`department`,`note`, ?, `hours_to_graduate`, NOW()
                   FROM `degree_maps` WHERE `id` = ?'
            );
            $stmt->bind_param('ii', $newYear, $sourceId);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException('Source map not found.');
            }
            $newId = (int) $this->db->insert_id;
            $stmt->close();

            // Footnotes, remembering old id => new id.
            $map  = [];
            $sel  = $this->db->prepare('SELECT * FROM `degree_maps_footnotes` WHERE `degree_map_id` = ? ORDER BY `order`');
            $ins  = $this->db->prepare('INSERT INTO `degree_maps_footnotes` (`degree_map_id`,`order`,`note`,`timestamp`) VALUES (?,?,?,NOW())');
            $sel->bind_param('i', $sourceId);
            $sel->execute();
            foreach ($sel->get_result()->fetch_all(MYSQLI_ASSOC) as $f) {
                $order = (int) $f['order'];
                $ins->bind_param('iis', $newId, $order, $f['note']);
                $ins->execute();
                $map[(int) $f['id']] = (int) $this->db->insert_id;
            }
            $sel->close();
            $ins->close();

            // Courses with footnote ids remapped.
            $sel = $this->db->prepare('SELECT * FROM `degree_maps_courses` WHERE `degree_map_id` = ? ORDER BY `year`,`semester`,`order`');
            $ins = $this->db->prepare(
                'INSERT INTO `degree_maps_courses`
                    (`degree_map_id`,`course_info`,`hours`,`footnote_ids`,`sge`,`extra`,`order`,`semester`,`year`,`scbcrse_subj_code`,`scbcrse_crse_numb`,`timestamp`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())'
            );
            $sel->bind_param('i', $sourceId);
            $sel->execute();
            foreach ($sel->get_result()->fetch_all(MYSQLI_ASSOC) as $c) {
                $fids = null;
                $old  = $c['footnote_ids'] ? json_decode((string) $c['footnote_ids'], true) : null;
                if (is_array($old)) {
                    $new  = array_values(array_filter(array_map(static fn ($i) => $map[(int) $i] ?? null, $old)));
                    $fids = $new === [] ? null : json_encode($new);
                }
                $ins->bind_param('isssssiiiss', $newId, $c['course_info'], $c['hours'], $fids, $c['sge'], $c['extra'],
                    $c['order'], $c['semester'], $c['year'], $c['scbcrse_subj_code'], $c['scbcrse_crse_numb']);
                $ins->execute();
            }
            $sel->close();
            $ins->close();

            foreach ([
                'INSERT INTO `degree_maps_semester_hours` (`degree_map_id`,`year`,`semester`,`hours`,`timestamp`) SELECT ?, `year`, `semester`, `hours`, NOW() FROM `degree_maps_semester_hours` WHERE `degree_map_id` = ?',
                'INSERT INTO `degree_maps_year_hours` (`degree_map_id`,`year`,`hours`,`timestamp`) SELECT ?, `year`, `hours`, NOW() FROM `degree_maps_year_hours` WHERE `degree_map_id` = ?',
            ] as $sql) {
                $st = $this->db->prepare($sql);
                $st->bind_param('ii', $newId, $sourceId);
                $st->execute();
                $st->close();
            }

            $this->db->commit();
            return $newId;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function touch(int $mapId): void
    {
        $stmt = $this->db->prepare('UPDATE `degree_maps` SET `timestamp` = NOW() WHERE `id` = ?');
        $stmt->bind_param('i', $mapId);
        $stmt->execute();
        $stmt->close();
    }
}
