<?php
namespace local_scorm_lom;

defined('MOODLE_INTERNAL') || die();

class observer {

    // grade_item.id → shortname do campo de perfil
    const MAPA_ILS = [
        12 => 'estilo_atiref',
        13 => 'estilo_semint',
        14 => 'estilo_visver',
        15 => 'estilo_seqglo',
    ];

    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;

        $userid    = $event->relateduserid;
        $quizid    = $event->other['quizid'];
        $attemptid = $event->objectid;

        // Verifica se é um dos quizzes ILS
        $giid = array_search(
            true,
            array_map(
                fn($id) => $DB->record_exists('grade_items', [
                    'id'           => $id,
                    'itemmodule'   => 'quiz',
                    'iteminstance' => $quizid,
                ]),
                array_keys(self::MAPA_ILS)
            )
        );

        // Busca o grade_item correto para esse quiz
        $gi = $DB->get_record_sql(
            "SELECT gi.id, gi.grademax
             FROM {grade_items} gi
             WHERE gi.itemmodule = 'quiz'
               AND gi.iteminstance = :quizid
               AND gi.id IN (" . implode(',', array_keys(self::MAPA_ILS)) . ")",
            ['quizid' => $quizid]
        );

        if (!$gi) {
            return; // não é um quiz ILS
        }

        $shortname = self::MAPA_ILS[$gi->id] ?? null;
        if (!$shortname) {
            return;
        }

        // Pega a nota final do aluno nesse grade_item
        $grade = $DB->get_record('grade_grades', [
            'itemid' => $gi->id,
            'userid' => $userid,
        ]);

        if (!$grade || $grade->finalgrade === null) {
            // Nota ainda não processada — aguarda o cron do Gradebook
            // Agenda para salvar depois via flag
            self::agendar_sincronizacao($userid, $gi->id, $shortname);
            return;
        }

        self::salvar_escala($userid, $gi->id, (float)$grade->finalgrade, $shortname);
    }

    private static function salvar_escala(int $userid, int $giid, float $nota_bruta, string $shortname): void {
        global $DB;

        // Aplica fórmula de Felder-Silverman: (2 × nota_bruta) - 11
        $escala = (int)round(($nota_bruta * 2) - 11);
        $escala = max(-11, min(11, $escala)); // garante intervalo

        $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => $shortname]);
        if (!$fieldid) {
            return;
        }

        $existente = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fieldid]);
        if ($existente) {
            $existente->data = $escala;
            $DB->update_record('user_info_data', $existente);
        } else {
            $rec             = new \stdClass();
            $rec->userid     = $userid;
            $rec->fieldid    = $fieldid;
            $rec->data       = $escala;
            $rec->dataformat = 0;
            $DB->insert_record('user_info_data', $rec);
        }
    }

    // Salva flag na tabela local_scorm_lom para o cron processar depois
    private static function agendar_sincronizacao(int $userid, int $giid, string $shortname): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_scorm_lom_ils')) {
            return;
        }

        $existe = $DB->record_exists('local_scorm_lom_ils', ['userid' => $userid, 'giid' => $giid]);
        if (!$existe) {
            $rec           = new \stdClass();
            $rec->userid   = $userid;
            $rec->giid     = $giid;
            $rec->shortname = $shortname;
            $rec->timecreated = time();
            $DB->insert_record('local_scorm_lom_ils', $rec);
        }
    }
}
