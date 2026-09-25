<?php

/** Page names (basenames) for new programs: ProgramEditor::basenameFor / cleanBasename. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Majors\Majors\ProgramEditor;

echo "[basename]\n";
check(ProgramEditor::basenameFor('Data Science', 'MS', "Master's") === 'data_science_ms', 'name + type');
check(ProgramEditor::basenameFor('Cybersecurity', '', 'Minor') === 'cybersecurity_minor', 'credential when there is no type');
check(ProgramEditor::basenameFor("Bachelor\u{2019}s to Master\u{2019}s in Business", '', "Bachelor's to Master's") === 'bachelors_to_masters_in_business_bachelors_to_masters', 'apostrophes (straight and curly) vanish');
check(ProgramEditor::basenameFor('Éducation – French (PreK-12)', 'BAED') === 'education_french_prek_12_baed', 'accents transliterated, punctuation collapsed');
check(ProgramEditor::basenameFor('  Cyber--Security  ', '', 'Minor') === 'cyber_security_minor', 'runs of separators collapse');
check(ProgramEditor::basenameFor('', '', '') === 'program', 'nothing left → program');
check(ProgramEditor::cleanBasename('Data-Science!! ') === 'data_science', 'typed page names are cleaned the same way');
check(strlen(ProgramEditor::basenameFor(str_repeat('abcdefghij', 20), 'MS')) === 120, 'clipped to 120 characters');

echo "[flags from credential]\n";
check(ProgramEditor::flagsFor("Master's") === ['graduate' => 1, 'minor' => 0, 'certificate' => 0, 'badge' => 0], "Master's → graduate");
check(ProgramEditor::flagsFor('Graduate Certificate') === ['graduate' => 1, 'minor' => 0, 'certificate' => 1, 'badge' => 0], 'Graduate Certificate → graduate + certificate');
check(ProgramEditor::flagsFor('Undergraduate Certificate') === ['graduate' => 0, 'minor' => 0, 'certificate' => 1, 'badge' => 0], 'Undergraduate Certificate → certificate only');
check(ProgramEditor::flagsFor('Minor')['minor'] === 1 && ProgramEditor::flagsFor('Badge')['badge'] === 1 && ProgramEditor::flagsFor('Major') === ['graduate' => 0, 'minor' => 0, 'certificate' => 0, 'badge' => 0], 'Minor, Badge, Major');

finish();
