<?php
/**
 * @package    local_cohortsyncup1
 * @copyright  2012-2014 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function local_cohortsyncup1_extend_navigation() {
	global $OUTPUT, $PAGE;
	
	$pages = ['/cohort/index.php'];
	if ($PAGE->has_set_url() && in_array($PAGE->url->get_path(), $pages)) {
		
		$linkItem = '<a href="'
        . htmlspecialchars(new moodle_url('/local/cohortsyncup1/viewcohort.php', ['id' => '123XYZ321']))
        . '" title="' . htmlspecialchars(get_string("info")) . '">'
        . $OUTPUT->pix_icon('i/info', get_string("info"))
        . '</a>';
        
        $addaction = htmlspecialchars(get_string('edit'));
		$acc = json_encode($addaction);
		
		$enc = json_encode($linkItem);
		$PAGE->requires->js_init_code(<<<EOJS
    var tableau = document.getElementById("cohorts");
    if (tableau !== null) {
		var ajout = 0;
		var rang = 0;
		var posname = 0;
		if (tableau.tHead) {
			var entetes = tableau.tHead.rows.item(0);
			var pos = entetes.children.length;
			var last = entetes.children[entetes.children.length-1];
			var classes = last.getAttribute("class");
			rang = entetes.children.length-1;
			if (classes.indexOf('action') === -1) {
				ajout = 1;
				rang = entetes.children.length;
			}
			
			for (var i = 0; i < entetes.children.length; i++) {
				var th = entetes.children[i];
				var thclasses = th.getAttribute("class");
				var result = thclasses.indexOf('name');
				if (thclasses.indexOf('name') !== -1) {
					posname = i;
				}
			}
			
			if (ajout === 1) {
				th = document.createElement('th');
				th.className = "header c"+rang+" lastcol centeralign action";
				th.setAttribute("scope", "col");
				th.innerHTML = $acc;
				entetes.appendChild(th);
				entetes.children.item(rang-1).classList.remove("lastcol");
			}
			
			for (var i = 1; i < tableau.rows.length; i++) {
				var ligne = tableau.rows.item(i);
				var id = ligne.cells.item(posname).children[0].getAttribute("data-itemid");
				
				if (ajout === 1) {
					ligne.cells.item(rang-1).classList.remove("lastcol");
					var cell = ligne.insertCell();
					cell.className = "centeralign action cell c"+rang+" lastcol";
					cell.innerHTML = $enc.replace('123XYZ321', id);
				} else {
					var ajout = ' ' + $enc.replace('123XYZ321', id);
					ligne.cells.item(rang).innerHTML += ajout;
				}
			}
		}
	}
EOJS
    , true);
			
	}
}
