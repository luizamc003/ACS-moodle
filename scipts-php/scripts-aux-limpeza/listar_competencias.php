<?php
define('CLI_SCRIPT', true);
require('/var/www/html/moodle/config.php');
global $DB;

$frameworks = $DB->get_records('competency_framework', [], 'id', 'id, shortname, idnumber');
echo "=== FRAMEWORKS ===\n";
foreach ($frameworks as $f) {
    echo "id={$f->id} shortname={$f->shortname} idnumber={$f->idnumber}\n";
}

echo "\n=== COMPETÊNCIAS ===\n";
$comps = $DB->get_records('competency', [], 'competencyframeworkid, parentid, id', 'id, shortname, idnumber, parentid, competencyframeworkid');
foreach ($comps as $c) {
    echo "id={$c->id} framework={$c->competencyframeworkid} parent={$c->parentid} idnumber={$c->idnumber} shortname={$c->shortname}\n";
}

echo "\n=== SCORMs curso 2 (primeiros 5) ===\n";
$scorms = $DB->get_records('scorm', ['course' => 2], 'id', 'id, name', 0, 5);
foreach ($scorms as $s) {
    $cm = $DB->get_record('course_modules', ['instance' => $s->id, 'module' => $DB->get_field('modules', 'id', ['name' => 'scorm'])]);
    echo "scorm id={$s->id} cmid=" . ($cm ? $cm->id : 'N/A') . " name={$s->name}\n";
}
echo "Total SCORMs: " . $DB->count_records('scorm', ['course' => 2]) . PHP_EOL;
