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

finish();
