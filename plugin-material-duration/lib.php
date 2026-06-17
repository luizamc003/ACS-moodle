<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Adiciona o campo "Tempo estimado (min)" no formulário de qualquer módulo.
 * Só exibe quando o módulo sendo editado é um SCORM.
 */
function local_scorm_lom_coursemodule_standard_elements($formwrapper, $mform) {
    if ($formwrapper->get_current()->modulename !== 'scorm') {
        return;
    }

    $mform->addElement('header', 'scorm_lom_header', get_string('pluginname', 'local_scorm_lom'));

    $mform->addElement(
        'text',
        'scorm_lom_estimated_time',
        get_string('estimated_time', 'local_scorm_lom'),
        ['size' => 5]
    );
    $mform->setType('scorm_lom_estimated_time', PARAM_INT);
    $mform->addHelpButton('scorm_lom_estimated_time', 'estimated_time', 'local_scorm_lom');
    $mform->addRule('scorm_lom_estimated_time', null, 'numeric', null, 'client');

    // Pré-popula com valor já salvo (edição)
    $cmid = $formwrapper->get_coursemodule()->id ?? 0;
    if ($cmid) {
        global $DB;
        $rec = $DB->get_record('local_scorm_lom', ['cmid' => $cmid]);
        if ($rec) {
            $mform->setDefault('scorm_lom_estimated_time', $rec->estimated_time);
        }
    }
}

/**
 * Valida o campo antes de salvar.
 */
function local_scorm_lom_coursemodule_validation($data, $files) {
    $errors = [];
    if (isset($data['scorm_lom_estimated_time']) && $data['scorm_lom_estimated_time'] !== '') {
        $val = (int)$data['scorm_lom_estimated_time'];
        if ($val < 0 || $val > 9999) {
            $errors['scorm_lom_estimated_time'] = get_string('error_invalid_time', 'local_scorm_lom');
        }
    }
    return $errors;
}

/**
 * Salva o tempo estimado na tabela local_scorm_lom após criar/editar o SCORM.
 */
function local_scorm_lom_coursemodule_edit_post_actions($moduleinfo, $course) {
    global $DB;

    if ($moduleinfo->modulename !== 'scorm') {
        return $moduleinfo;
    }

    $cmid = $moduleinfo->coursemodule;
    $tempo = isset($moduleinfo->scorm_lom_estimated_time)
        ? (int)$moduleinfo->scorm_lom_estimated_time
        : null;

    $existente = $DB->get_record('local_scorm_lom', ['cmid' => $cmid]);

    if ($existente) {
        $existente->estimated_time = $tempo;
        $DB->update_record('local_scorm_lom', $existente);
    } else {
        $rec = new stdClass();
        $rec->cmid           = $cmid;
        $rec->estimated_time = $tempo;
        $DB->insert_record('local_scorm_lom', $rec);
    }

    return $moduleinfo;
}
