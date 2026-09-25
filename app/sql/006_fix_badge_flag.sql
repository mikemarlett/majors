-- 006: the first import (2026-09-24) set badge=1 on nearly every program (operator-precedence
-- bug in the importer, fixed 2026-09-25). A badge is a program whose credential says so.
UPDATE `majors_academic_programs` SET `badge` = (`credential` LIKE '%Badge%');
