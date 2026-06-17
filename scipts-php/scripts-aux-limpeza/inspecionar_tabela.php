<?php
define('CLI_SCRIPT', true);
require('/var/www/html/moodle/config.php');
global $DB;

// Mostra colunas da tabela de associação scorm-competência
$tabelas = [
    'competency_coursemodulecomp',
    'competency_modulecomp',
];

foreach ($tabelas as $tabela) {
    try {
        $cols = $DB->get_columns($tabela);
        echo "=== {$tabela} ===\n";
        foreach ($cols as $col) {
            echo "  {$col->name} ({$col->meta_type})\n";
        }
    } catch (Exception $e) {
        echo "=== {$tabela} — NÃO EXISTE ===\n";
    }
}
