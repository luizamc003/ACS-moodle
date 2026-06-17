<?php
/**
 * Corrige SCORMs já criados que estão sem o SCO de organização (pai).
 * Sem esse registro o player do Moodle quebra com array_keys(null).
 *
 * Uso:
 *   sudo php /home/luiza/Desktop/TCC/corrigir_scoes_org.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/config.php');

global $DB, $USER;
$USER = get_admin();

// Busca todos os SCORMs do curso 2
$scorms = $DB->get_records('scorm', ['course' => 2], 'id', 'id, name, launch');

$corrigidos = 0;
$pulados    = 0;

foreach ($scorms as $scorm) {
    // Extrai o id do material a partir do identifier do SCO filho
    $sco_filho = $DB->get_record('scorm_scoes', [
        'scorm'     => $scorm->id,
        'scormtype' => 'sco',
    ]);

    if (!$sco_filho) {
        echo "[SEM SCO] scorm id={$scorm->id} nome={$scorm->name}\n";
        continue;
    }

    // Verifica se o SCO de organização (pai) já existe
    $ja_tem_org = $DB->record_exists('scorm_scoes', [
        'scorm'     => $scorm->id,
        'scormtype' => '',
        'parent'    => '',
    ]);

    if ($ja_tem_org) {
        $pulados++;
        continue;
    }

    // Cria o SCO pai (organização)
    $org_identifier = "ORG-" . preg_replace('/^ITEM-/', '', $sco_filho->identifier);

    $sco_org = new stdClass();
    $sco_org->scorm        = $scorm->id;
    $sco_org->manifest     = $sco_filho->manifest;
    $sco_org->organization = '';
    $sco_org->parent       = '';
    $sco_org->identifier   = $org_identifier;
    $sco_org->launch       = '';
    $sco_org->scormtype    = '';
    $sco_org->title        = $scorm->name;
    $sco_org->sortorder    = 0;
    $DB->insert_record('scorm_scoes', $sco_org);

    // Garante que o SCO filho aponta para a organização como parent
    if ($sco_filho->parent !== $org_identifier) {
        $DB->set_field('scorm_scoes', 'parent', $org_identifier, ['id' => $sco_filho->id]);
        $DB->set_field('scorm_scoes', 'organization', $org_identifier, ['id' => $sco_filho->id]);
    }

    echo "[OK] scorm id={$scorm->id} — criado SCO org '{$org_identifier}' para '{$scorm->name}'\n";
    $corrigidos++;
}

rebuild_course_cache(2, true);

echo "\n═══════════════════════════════════════════════════════\n";
echo "Corrigidos: {$corrigidos} | Já estavam corretos: {$pulados}\n";
