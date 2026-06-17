<?php
/**
 * NÃO UTILIZADO
 * Lê as notas brutas dos 4 quizzes ILS, aplica (2×nota)-11
 * e salva o resultado nos campos de perfil do aluno.
 *
 * Execute após cada rodada de respostas:
 *   sudo php /home/luiza/Desktop/TCC/sincronizar_estilo_perfil.php
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle/public/config.php');
global $DB;

define('COURSE_ID', 2);

// grade_item id → campo de perfil
$mapa = [
    12 => 'estilo_atiref',
    13 => 'estilo_semint',
    14 => 'estilo_visver',
    15 => 'estilo_seqglo',
];

// Carrega ids dos campos de perfil
$campos = [];
foreach (array_values($mapa) as $shortname) {
    $f = $DB->get_record('user_info_field', ['shortname' => $shortname], 'id, shortname');
    if ($f)
        $campos[$f->shortname] = $f->id;
}
echo "Campos de perfil carregados: " . implode(', ', array_keys($campos)) . "\n\n";

// Busca todas as notas finais dos quizzes ILS
$notas = $DB->get_records_sql(
    "SELECT gg.userid, gg.itemid, gg.finalgrade
     FROM {grade_grades} gg
     WHERE gg.itemid IN (" . implode(',', array_keys($mapa)) . ")
       AND gg.finalgrade IS NOT NULL
     ORDER BY gg.userid"
);

if (!$notas) {
    echo "Nenhuma nota encontrada. Os alunos ainda não responderam os quizzes.\n";
    exit;
}

// Agrupa por aluno
$por_aluno = [];
foreach ($notas as $n) {
    $por_aluno[$n->userid][$n->itemid] = (float) $n->finalgrade;
}

$total_ok = 0;
$total_erros = 0;

foreach ($por_aluno as $userid => $notas_aluno) {
    echo "Aluno userid={$userid}:\n";

    foreach ($mapa as $giid => $shortname) {
        if (!isset($notas_aluno[$giid])) {
            echo "  [{$shortname}] sem nota ainda\n";
            continue;
        }

        $nota_bruta = $notas_aluno[$giid];
        $escala = (int) round(($nota_bruta * 2) - 11);

        // Garante que está no intervalo -11 a +11
        $escala = max(-11, min(11, $escala));

        $fieldid = $campos[$shortname] ?? null;
        if (!$fieldid) {
            echo "  [ERRO] campo de perfil '{$shortname}' não encontrado\n";
            $total_erros++;
            continue;
        }

        // Salva ou atualiza em user_info_data
        $existente = $DB->get_record('user_info_data', [
            'userid' => $userid,
            'fieldid' => $fieldid,
        ]);

        if ($existente) {
            $existente->data = $escala;
            $DB->update_record('user_info_data', $existente);
        } else {
            $rec = new stdClass();
            $rec->userid = $userid;
            $rec->fieldid = $fieldid;
            $rec->data = $escala;
            $rec->dataformat = 0;
            $DB->insert_record('user_info_data', $rec);
        }

        echo "  [{$shortname}] nota_bruta={$nota_bruta} → escala={$escala}\n";
        $total_ok++;
    }
    echo "\n";
}

echo "═══════════════════════════════════════════\n";
echo "Salvos: {$total_ok} | Erros: {$total_erros}\n";
