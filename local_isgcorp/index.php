<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Listagem pública de trilhas de aprendizagem, com busca, ordenação
 * e filtro por categoria.
 *
 * @package    local_isgcorp
 * @copyright  2026 Truly Tecnologia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$q = optional_param('q', '', PARAM_TEXT);
$cat = optional_param('cat', '', PARAM_ALPHA);
$sort = optional_param('sort', 'relevance', PARAM_ALPHA);

$PAGE->set_url(new moodle_url('/local/isgcorp/index.php', ['q' => $q, 'cat' => $cat, 'sort' => $sort]));
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('trilhas', 'local_isgcorp'));
// Não usamos $PAGE->set_heading() aqui de propósito — o Boost
// desenharia um <h1> automático em cima do nosso próprio título
// customizado ("Trilhas de Aprendizagem"), duplicando visualmente.

global $USER;

// Rótulos das categorias — precisam bater com as opções do
// formulário de cadastro (classes/form/trilha_form.php).
$categorylabels = [
    'tecnologia' => get_string('categoriatecnologia', 'local_isgcorp'),
    'lideranca' => get_string('categorialideranca', 'local_isgcorp'),
    'negocios' => get_string('categorianegocios', 'local_isgcorp'),
    'compliance' => get_string('categoriacompliance', 'local_isgcorp'),
    'saude' => get_string('categoriasaude', 'local_isgcorp'),
    'softskills' => get_string('categoriasoftskills', 'local_isgcorp'),
    'idiomas' => get_string('categoriaidiomas', 'local_isgcorp'),
    'outros' => get_string('categoriaoutros', 'local_isgcorp'),
];

$alltrilhas = local_isgcorp_get_trilhas(true);

// Categorias que realmente têm alguma trilha, na ordem fixa acima
// (não a ordem que apareceram no banco, pra manter as abas estáveis).
$categoriespresent = [];
foreach ($alltrilhas as $t) {
    if (!empty($t->category)) {
        $categoriespresent[$t->category] = true;
    }
}

// Aplica filtro de categoria.
$trilhas = $alltrilhas;
if ($cat !== '' && isset($categorylabels[$cat])) {
    $trilhas = array_filter($trilhas, function($t) use ($cat) {
        return $t->category === $cat;
    });
}

// Aplica busca por texto (nome ou descrição).
if ($q !== '') {
    $needle = core_text::strtolower($q);
    $trilhas = array_filter($trilhas, function($t) use ($needle) {
        $haystack = core_text::strtolower($t->name . ' ' . strip_tags($t->description));
        return strpos($haystack, $needle) !== false;
    });
}

// Aplica ordenação.
$trilhas = array_values($trilhas);
switch ($sort) {
    case 'name':
        usort($trilhas, fn($a, $b) => strcmp($a->name, $b->name));
        break;
    case 'hours':
        usort($trilhas, fn($a, $b) => $a->estimatedhours <=> $b->estimatedhours);
        break;
    default:
        usort($trilhas, fn($a, $b) => [$a->sortorder, $a->id] <=> [$b->sortorder, $b->id]);
}

echo $OUTPUT->header();

echo '<div class="isg-trilhas-page">';

echo '<div class="isg-trilhas-headrow">';
echo   '<div>';
echo     '<h1 class="isg-trilhas-title">' . get_string('trilhaspagetitle', 'local_isgcorp') . '</h1>';
echo     '<p class="isg-trilhas-subtitle">' . get_string('trilhaspagesubtitle', 'local_isgcorp') . '</p>';
echo   '</div>';
echo   '<form method="get" class="isg-trilhas-controls">';
echo     '<input type="text" name="q" class="isg-search-input" placeholder="' . get_string('searchtrilhas', 'local_isgcorp') . '" value="' . s($q) . '">';
if ($cat !== '') {
    echo '<input type="hidden" name="cat" value="' . s($cat) . '">';
}
echo     '<select name="sort" class="isg-sort-select" onchange="this.form.submit()">';
echo       '<option value="relevance" ' . ($sort === 'relevance' ? 'selected' : '') . '>' . get_string('sortrelevance', 'local_isgcorp') . '</option>';
echo       '<option value="name" ' . ($sort === 'name' ? 'selected' : '') . '>' . get_string('sortname', 'local_isgcorp') . '</option>';
echo       '<option value="hours" ' . ($sort === 'hours' ? 'selected' : '') . '>' . get_string('sorthours', 'local_isgcorp') . '</option>';
echo     '</select>';
echo     '<button type="submit" class="isg-search-btn">&#128269;</button>';
echo   '</form>';
echo '</div>';

// Abas de categoria.
echo '<div class="isg-trilha-tabs">';
$allurl = new moodle_url('/local/isgcorp/index.php', ['sort' => $sort]);
echo '<a class="isg-trilha-tab ' . ($cat === '' ? 'active' : '') . '" href="' . $allurl->out() . '">' . get_string('categoriatodos', 'local_isgcorp') . '</a>';
foreach ($categorylabels as $slug => $label) {
    if (!isset($categoriespresent[$slug])) {
        continue;
    }
    $taburl = new moodle_url('/local/isgcorp/index.php', ['cat' => $slug, 'sort' => $sort]);
    echo '<a class="isg-trilha-tab ' . ($cat === $slug ? 'active' : '') . '" href="' . $taburl->out() . '">' . $label . '</a>';
}
echo '</div>';

if (empty($trilhas)) {
    echo html_writer::div(get_string('notrilhaspublic', 'local_isgcorp'), 'isg-empty');
} else {
    echo '<div class="isg-trilha-grid">';

    foreach ($trilhas as $trilha) {
        $courses = local_isgcorp_get_trilha_courses((int) $trilha->id);
        $coursecount = $DB->count_records('local_isgcorp_trilha_course', ['trilhaid' => $trilha->id]);
        $progress = local_isgcorp_get_trilha_progress($trilha->id, $USER->id);
        $viewurl = new moodle_url('/local/isgcorp/view.php', ['id' => $trilha->id]);
        $levelstring = get_string('level' . $trilha->level, 'local_isgcorp');
        $coverurl = local_isgcorp_get_trilha_visual_url($trilha, $courses);
        $categorylabel = $categorylabels[$trilha->category] ?? get_string('trilhas', 'local_isgcorp');
        $description = trim(preg_replace('/\s+/', ' ', strip_tags(
            format_text($trilha->description, $trilha->descriptionformat)
        )));
        $progresslabel = $progress !== null
            ? $progress . '% ' . get_string('concluded', 'local_isgcorp')
            : get_string('notrackinglabel', 'local_isgcorp');
        $progressbarclass = $progress !== null ? 'isg-bar-red' : 'isg-bar-gray';
        $progresstextclass = $progress !== null ? 'isg-bar-red-text' : 'isg-trilha-progress-text-muted';
        $coverclass = 'isg-trilha-cover';
        $coverstyle = '';

        if ($coverurl) {
            $coverstyle = ' style="background-image:url(' . s($coverurl->out(false)) . ')"';
        } else {
            $coverclass .= ' isg-trilha-cover-fallback isg-trilha-cover-' . (($trilha->id % 5) + 1);
        }

        echo '<a class="isg-trilha-card" href="' . $viewurl->out() . '">';
        echo     '<div class="' . $coverclass . '"' . $coverstyle . '>';
        echo         '<span class="isg-trilha-chip">' . s($categorylabel) . '</span>';
        echo     '</div>';
        echo     '<div class="isg-trilha-body">';
        echo         '<div class="isg-trilha-title-row">';
        echo             '<div class="isg-trilha-title">' . format_string($trilha->name) . '</div>';
        echo             '<span class="isg-trilha-arrow">&rarr;</span>';
        echo         '</div>';
        if ($description !== '') {
            echo     '<div class="isg-trilha-desc">' . s($description) . '</div>';
        }
        echo         '<div class="isg-trilha-progress-wrap">';
        echo             '<span class="isg-trilha-progress-label ' . $progresstextclass . '">' . s($progresslabel) . '</span>';
        echo             '<div class="isg-course-progress-track">';
        echo                 '<div class="isg-course-progress-fill ' . $progressbarclass . '" style="width:' . ($progress ?? 0) . '%"></div>';
        echo             '</div>';
        echo         '</div>';

        echo         '<div class="isg-trilha-meta">';
        echo             '<span class="isg-trilha-meta-item">&#128214; ' . $coursecount . ' ' . get_string('coursescount', 'local_isgcorp') . '</span>';
        echo             '<span class="isg-trilha-meta-item">&#128202; ' . s($levelstring) . '</span>';
        echo         '</div>';
        echo     '</div>';
        echo '</a>';
    }

    echo '</div>';
}

echo '</div>';

echo $OUTPUT->footer();
