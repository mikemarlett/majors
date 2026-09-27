<?php

declare(strict_types=1);

namespace Majors\Majors;

use Majors\Support\Html;

/**
 * The listing pages and what a program's listing entries mean.
 *
 * A program appears on the listing pages through its entries (majors_listing_entries):
 * each entry is one line (a name, a detail after the dash) on the lists it is ticked for,
 * shown in the A–Z view, the by-college view or both. No entries = not listed; the page
 * itself stays live. The lists and their public addresses match the CMS pages they replace.
 */
final class Listings
{
    /** list key => label, A–Z page, by-college page (null: none), page headline */
    public const LISTS = [
        'all'          => ['label' => 'All Programs',              'page' => 'index.php',        'college' => 'index_by_college.php',    'headline' => 'All Degrees'],
        'undergrad'    => ['label' => 'Undergrad Majors & Minors', 'page' => 'majors.php',       'college' => 'majors_by_college.php',   'headline' => 'Undergraduate Majors and Minors'],
        'graduate'     => ['label' => 'Graduate Degrees',          'page' => 'graduate.php',     'college' => 'graduate_by_college.php', 'headline' => 'Graduate Degrees'],
        'online'       => ['label' => 'Online',                    'page' => 'online.php',       'college' => 'online_by_college.php',   'headline' => 'Online Programs'],
        'certificates' => ['label' => 'Certificates',              'page' => 'certificates.php', 'college' => null,                      'headline' => 'Certificates'],
        'badges'       => ['label' => 'Badges',                    'page' => null,               'college' => null,                      'headline' => 'Badges'],
    ];

    /** Short labels for the control panel's pills. */
    public const SHORT = ['all' => 'All', 'undergrad' => 'UG', 'graduate' => 'Grad', 'online' => 'Online', 'certificates' => 'Cert', 'badges' => 'Badge'];

    public const SHOWN_IN = ['both' => 'A–Z and by college', 'az' => 'A–Z only', 'college' => 'By college only'];

    public const CERT_SECTIONS = ['graduate' => 'Graduate Certificates', 'undergraduate' => 'Undergraduate Certificates'];

    /** Topics on the Certificates page, in page order. */
    public const CERT_TOPICS = ['Education', 'Health', 'Business and Management', 'Science and Engineering', 'Arts and Humanities', 'General Topics'];

    /** Badges live on their own site; the listing links there, as the CMS pages do. */
    public const BADGES_URL = 'https://badges.wichita.edu/badge/';

    /** Degree names the listings use, by program type (taken from the CMS lists). */
    private const DEGREES = [
        'AuD' => 'Doctor of Audiology', 'BA' => 'Bachelor of Arts', 'BAA' => 'Bachelor of Applied Arts', 'BAED' => 'Bachelor of Arts in Education',
        'BAS' => 'Bachelor of Applied Science', 'BBA' => 'Bachelor of Business Administration', 'BFA' => 'Bachelor of Fine Arts',
        'BGS' => 'Bachelor of General Studies', 'BM' => 'Bachelor of Music', 'BME' => 'Bachelor of Music Education', 'BSME' => 'Bachelor of Music Education',
        'BS' => 'Bachelor of Science', 'BSN' => 'Bachelor of Science in Nursing', 'BSW' => 'Bachelor of Social Work',
        'DNP' => 'Doctor of Nursing Practice', 'DPT' => 'Doctor of Physical Therapy', 'EdD' => 'Doctor of Education', 'EDS' => 'Education Specialist',
        'MA' => 'Master of Arts', 'MACC' => 'Master of Accountancy', 'MAT' => 'Master of Arts in Teaching', 'MBA' => 'Master of Business Administration',
        'MED' => 'Master of Education', 'MEM' => 'Master of Engineering Management', 'MFA' => 'Master of Fine Arts', 'MHA' => 'Master of Health Administration',
        'MHRM' => 'Master of Human Resource Management', 'MID' => 'Master of Innovation Design', 'MM' => 'Master of Music', 'MME' => 'Master of Music Education',
        'MPA' => 'Master of Physician Associate', 'MPH' => 'Master of Public Health', 'MS' => 'Master of Science', 'MSN' => 'Master of Science in Nursing',
        'MSW' => 'Master of Social Work', 'OTD' => 'Doctor of Occupational Therapy', 'PhD' => 'Doctor of Philosophy', 'MAJOR' => 'Field Major',
        'Certificate - Graduate' => 'Graduate Certificate', 'Certificate - Undergraduate' => 'Undergraduate Certificate',
    ];

    /**
     * The lists a new program starts on, the way the CMS lists treat each credential:
     * certificates, endorsements and practicums on Certificates only; master's and doctoral
     * degrees on Graduate; majors, minors, field majors and bachelor's-to-master's on Undergrad.
     *
     * @return array{lists:list<string>,cert_section:?string}
     */
    public static function defaults(array $p): array
    {
        $cred  = trim((string) ($p['credential'] ?? ''));
        $lists = ['all'];
        $cert  = null;
        if (stripos($cred, 'Certificate') !== false || in_array($cred, ['Endorsement', 'Practicum Placement'], true) || (!empty($p['certificate']) && $cred === '')) {
            $lists[] = 'certificates';
            $cert = stripos($cred, 'Undergraduate') !== false || ($cred === '' && empty($p['graduate'])) ? 'undergraduate' : 'graduate';
        } elseif (stripos($cred, 'Badge') !== false || (!empty($p['badge']) && $cred === '')) {
            $lists[] = 'badges';
        } elseif (preg_match("/^(Master's|Doctorate|Postbaccalaureate|Post Master|Graduate Emphasis)$/", $cred) || ($cred === '' && !empty($p['graduate']))) {
            $lists[] = 'graduate';
        } else {
            $lists[] = 'undergrad';
        }
        if (!empty($p['online_learning']) || !empty($p['online_only'])) {
            $lists[] = 'online';
        }
        return ['lists' => $lists, 'cert_section' => $cert];
    }

    /** The degree written out, for an entry without its own detail ("Master of Science", "Minor"). */
    public static function degreeName(array $p): string
    {
        $type = trim((string) ($p['program_type'] ?? ''));
        $cred = trim((string) ($p['credential'] ?? ''));
        if (isset(self::DEGREES[$type])) {
            return self::DEGREES[$type];
        }
        if (in_array($type, ['Minor', 'Endorsement', 'Practicum Placement', "Bachelor's to Master's", 'Undergraduate Emphasis'], true)) {
            return $type;
        }
        return $cred !== '' ? $cred : $type;
    }

    /** The line's name: its own, or the program's. */
    public static function name(array $entry, array $p): string
    {
        $n = trim((string) ($entry['name'] ?? ''));
        return $n !== '' ? $n : (string) ($p['academic_program'] ?? '');
    }

    /** The line's detail as HTML: its own (cleaned when saved), or the degree written out. */
    public static function detailHtml(array $entry, array $p): string
    {
        $d = trim((string) ($entry['detail'] ?? ''));
        return $d !== '' ? $d : Html::e(self::degreeName($p));
    }

    /** @return list<string> the list keys an entry is on */
    public static function listsOf(array $entry): array
    {
        return array_values(array_filter(explode(',', (string) ($entry['lists'] ?? ''))));
    }

    /** Public address of a list view. */
    public static function url(string $baseUrl, string $list, string $order = 'alpha'): string
    {
        if ($list === 'badges') {
            return self::BADGES_URL;
        }
        $def  = self::LISTS[$list] ?? self::LISTS['all'];
        $page = $order === 'college' && $def['college'] !== null ? $def['college'] : $def['page'];
        return rtrim($baseUrl, '/') . '/' . $page;
    }
}
