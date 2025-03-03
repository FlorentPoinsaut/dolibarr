<?php
/* Copyright (C) 2001-2003  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2018  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2013       Cédric Salvador         <csalvador@gpcsolutions.fr>
 * Copyright (C) 2019       Thibault FOUCART        <support@ptibogxiv.net>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/don/list.php
 *  \ingroup    donations
 *  \brief      List of donations
 */

// Load Dolibarr environment
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/don/class/don.class.php';
if (isModEnabled('project')) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array('companies', 'donations'));

// Get parameters
$action     = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view'; // The action 'create'/'add', 'edit'/'update', 'view', ...
$massaction = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$show_files = GETPOSTINT('show_files'); // Show files area generated by bulk actions ?
$confirm    = GETPOST('confirm', 'alpha'); // Result of a confirmation
$cancel     = GETPOST('cancel', 'alpha'); // We click on a Cancel button
$toselect   = GETPOST('toselect', 'array:int'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : str_replace('_', '', basename(dirname(__FILE__)).basename(__FILE__, '.php')); // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page
$optioncss  = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')
$mode       = GETPOST('mode', 'aZ'); // The display mode ('list', 'kanban', 'hierarchy', 'calendar', 'gantt', ...)
$groupby = GETPOST('groupby', 'aZ09');	// Example: $groupby = 'p.fk_opp_status' or $groupby = 'p.fk_statut'

$type = GETPOST('type', 'aZ');

$search_status = (GETPOST("search_status", 'intcomma') != '') ? GETPOST("search_status", 'intcomma') : "-4";
$search_all = trim(GETPOST('search_all', 'alphanohtml'));
$search_ref = GETPOST('search_ref', 'alpha');
$search_company = GETPOST('search_company', 'alpha');
$search_thirdparty = GETPOST('search_thirdparty', 'alpha');
$search_name = GETPOST('search_name', 'alpha');
$search_amount = GETPOST('search_amount', 'alpha');
$moreforfilter = GETPOST('moreforfilter', 'alpha');

// Load variable for pagination
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	// If $page is not defined, or '' or -1 or if we click on clear filters
	$page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize a technical objects
$object = new Don($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->don->dir_output.'/temp/massgeneration/'.$user->id;
$hookmanager->initHooks(array($contextpage)); 	// Note that conf->hooks_modules contains array of activated contexes

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);
//$extrafields->fetch_name_optionals_label($object->table_element_line);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Default sort order (if not yet defined by previous GETPOST)
if (!$sortorder) {
	$sortorder = "DESC";
}
if (!$sortfield) {
	$sortfield = "d.datedon";
}

// Initialize array of search criteria
$search_all = trim(GETPOST('search_all', 'alphanohtml'));
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha') !== '') {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
	if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
		$search[$key.'_dtstart'] = dol_mktime(0, 0, 0, GETPOSTINT('search_'.$key.'_dtstartmonth'), GETPOSTINT('search_'.$key.'_dtstartday'), GETPOSTINT('search_'.$key.'_dtstartyear'));
		$search[$key.'_dtend'] = dol_mktime(23, 59, 59, GETPOSTINT('search_'.$key.'_dtendmonth'), GETPOSTINT('search_'.$key.'_dtendday'), GETPOSTINT('search_'.$key.'_dtendyear'));
	}
}

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(
	'd.rowid' => 'Id',
	'd.ref' => 'Ref',
	'd.lastname' => 'Lastname',
	'd.firstname' => 'Firstname',
);
// Extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';

$object->fields = dol_sort_array($object->fields, 'position');
//$arrayfields['anotherfield'] = array('type'=>'integer', 'label'=>'AnotherField', 'checked'=>1, 'enabled'=>1, 'position'=>90, 'csslist'=>'right');
$arrayfields = dol_sort_array($arrayfields, 'position');


// Security check
$result = restrictedArea($user, 'don');

$permissiontoread = $user->hasRight('don', 'read');
$permissiontoadd = $user->hasRight('don', 'write');
$permissiontodelete = $user->hasRight('don', 'delete');


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		foreach ($object->fields as $key => $val) {
			$search[$key] = '';
			if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
				$search[$key.'_dtstart'] = '';
				$search[$key.'_dtend'] = '';
			}
		}

		$search_all = "";
		$search_ref = "";
		$search_company = "";
		$search_thirdparty = "";
		$search_name = "";
		$search_amount = "";
		$search_status = '';
		$toselect = array();
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass = 'Don';
	$objectlabel = 'Don';
	$uploaddir = $conf->don->dir_output;

	global $error;
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';

	// You can add more action here
	// if ($action == 'xxx' && $permissiontoxxx) ...
}



/*
 * View
 */

$form = new Form($db);

$now = dol_now();

$donationstatic = new Don($db);
if (isModEnabled('project')) {
	$projectstatic = new Project($db);
}

$title = $langs->trans("Donations");
$help_url = 'EN:Module_Donations|FR:Module_Dons|ES:M&oacute;dulo_Donaciones|DE:Modul_Spenden';
$morejs = array();
$morecss = array();

// Build and execute select
// --------------------------------------------------------------------
$sql = "SELECT d.rowid, d.datedon, d.fk_soc as socid, d.firstname, d.lastname, d.societe,";
$sql .= " d.amount, d.fk_statut as status,";
$sql .= " p.rowid as pid, p.ref, p.title, p.public";
// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;
$sql = preg_replace('/,\s*$/', '', $sql);

$sqlfields = $sql; // $sql fields to remove for count total

$sql .= " FROM ".MAIN_DB_PREFIX.$object->table_element." as d LEFT JOIN ".MAIN_DB_PREFIX."projet AS p";
$sql .= " ON p.rowid = d.fk_projet";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe AS s ON s.rowid = d.fk_soc";
$sql .= " WHERE d.entity IN (". getEntity('donation') . ")";

if ($search_status != '' && $search_status != '-4') {
	$sql .= " AND d.fk_statut IN (".$db->sanitize($search_status).")";
}
if (trim($search_ref) != '') {
	$sql .= natural_search(array('d.ref', "d.rowid"), $search_ref);
}
if (trim($search_all) != '') {
	$sql .= natural_search(array_keys($fieldstosearchall), $search_all);
}
if (trim($search_company) != '') {
	$sql .= natural_search('d.societe', $search_company);
}
if (trim($search_thirdparty) != '') {
	$sql .= natural_search("s.nom", $search_thirdparty);
}
if (trim($search_name) != '') {
	$sql .= natural_search(array('d.lastname', 'd.firstname'), $search_name);
}
if ($search_amount) {
	$sql .= natural_search('d.amount', $search_amount, 1);
}

// Count total nb of records
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	/* The fast and low memory method to get and count full list converts the sql into a sql count */
	$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) as nbtotalofrecords', $sql);
	$sqlforcount = preg_replace('/GROUP BY .*$/', '', $sqlforcount);
	$resql = $db->query($sqlforcount);
	if ($resql) {
		$objforcount = $db->fetch_object($resql);
		$nbtotalofrecords = $objforcount->nbtotalofrecords;
	} else {
		dol_print_error($db);
	}

	if (($page * $limit) > $nbtotalofrecords) {	// if total resultset is smaller than the paging size (filtering), goto and load page 0
		$page = 0;
		$offset = 0;
	}
	$db->free($resql);
}

// Complete request and execute it with limit
$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
	$sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);

// Direct jump if only one record found
if ($num == 1 && getDolGlobalInt('MAIN_SEARCH_DIRECT_OPEN_IF_ONLY_ONE') && $search_all && !$page) {
	$obj = $db->fetch_object($resql);
	$id = $obj->rowid;
	header("Location: ".dol_buildpath('/mymodule/myobject_card.php', 1).'?id='.((int) $id));
	exit;
}


// Output page
// --------------------------------------------------------------------

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss, '', 'mod-donation page-list bodyforlist');	// Can use also classforhorizontalscrolloftabs instead of bodyforlist for no horizontal scroll

// Example : Adding jquery code
// print '<script type="text/javascript">
// jQuery(document).ready(function() {
// 	function init_myfunc()
// 	{
// 		jQuery("#myid").removeAttr(\'disabled\');
// 		jQuery("#myid").attr(\'disabled\',\'disabled\');
// 	}
// 	init_myfunc();
// 	jQuery("#mybutton").click(function() {
// 		init_myfunc();
// 	});
// });
// </script>';

$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';
if (!empty($mode)) {
	$param .= '&mode='.urlencode($mode);
}
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}
if ($optioncss != '') {
	$param .= '&optioncss='.urlencode($optioncss);
}
if ($groupby != '') {
	$param .= '&groupby='.urlencode($groupby);
}
if ($search_status && $search_status != -1) {
	$param .= '&search_status='.urlencode($search_status);
}
if ($search_ref) {
	$param .= '&search_ref='.urlencode($search_ref);
}
if ($search_company) {
	$param .= '&search_company='.urlencode($search_company);
}
if ($search_name) {
	$param .= '&search_name='.urlencode($search_name);
}
if ($search_amount) {
	$param .= '&search_amount='.urlencode($search_amount);
}
// Add $param from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';
// Add $param from hooks
$parameters = array('param' => &$param);
$reshook = $hookmanager->executeHooks('printFieldListSearchParam', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
$param .= $hookmanager->resPrint;

// List of mass actions available
$arrayofmassactions = array(
	//'validate'=>img_picto('', 'check', 'class="pictofixedwidth"').$langs->trans("Validate"),
	//'generate_doc'=>img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans("ReGeneratePDF"),
	//'builddoc'=>img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans("PDFMerge"),
	//'presend'=>img_picto('', 'email', 'class="pictofixedwidth"').$langs->trans("SendByMail"),
);
if (!empty($permissiontodelete)) {
	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
}
if (GETPOSTINT('nomassaction') || in_array($massaction, array('presend', 'predelete'))) {
	$arrayofmassactions = array();
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
if ($optioncss != '') {
	print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
print '<input type="hidden" name="page_y" value="">';
print '<input type="hidden" name="mode" value="'.$mode.'">';
print '<input type="hidden" name="type" value="'.$type.'">';

$newcardbutton = '';
$newcardbutton .= dolGetButtonTitle($langs->trans('ViewList'), '', 'fa fa-bars imgforviewmode', $_SERVER["PHP_SELF"].'?mode=common'.preg_replace('/(&|\?)*mode=[^&]+/', '', $param), '', ((empty($mode) || $mode == 'common') ? 2 : 1), array('morecss' => 'reposition'));
$newcardbutton .= dolGetButtonTitle($langs->trans('ViewKanban'), '', 'fa fa-th-list imgforviewmode', $_SERVER["PHP_SELF"].'?mode=kanban'.preg_replace('/(&|\?)*mode=[^&]+/', '', $param), '', ($mode == 'kanban' ? 2 : 1), array('morecss' => 'reposition'));
$newcardbutton .= dolGetButtonTitleSeparator();
$newcardbutton .= dolGetButtonTitle($langs->trans('NewDonation'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/don/card.php?action=create', '', $permissiontoadd);

// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'object_donation', 0, $newcardbutton, '', $limit, 0, 0, 1);

// Add code for pre mass action (confirmation or email presend form)
$topicmail = "SendDonationRef";
$modelmail = "don";
$objecttmp = new Don($db);
$trackid = 'don'.$object->id;
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

if ($search_all) {
	$setupstring = '';
	foreach ($fieldstosearchall as $key => $val) {
		$fieldstosearchall[$key] = $langs->trans($val);
		$setupstring .= $key."=".$val.";";
	}
	print '<!-- Search done like if DONATION_QUICKSEARCH_ON_FIELDS = '.$setupstring.' -->'."\n";
	print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $search_all).implode(', ', $fieldstosearchall).'</div>';
}

$moreforfilter = '';
/*$moreforfilter.='<div class="divsearchfield">';
$moreforfilter.= $langs->trans('MyFilter') . ': <input type="text" name="search_myfield" value="'.dol_escape_htmltag($search_myfield).'">';
$moreforfilter.= '</div>';*/

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
if (empty($reshook)) {
	$moreforfilter .= $hookmanager->resPrint;
} else {
	$moreforfilter = $hookmanager->resPrint;
}
$parameters = array(
	'arrayfields' => &$arrayfields,
);

if (!empty($moreforfilter)) {
	print '<div class="liste_titre liste_titre_bydiv centpercent">';
	print $moreforfilter;
	print '</div>';
}

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, $conf->main_checkbox_left_column ? 'left' : '');  // This also change content of $arrayfields with user setup
$selectedfields = ($mode != 'kanban' ? $htmlofselectarray : '');
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you don't need reserved height for your table
print '<table class="tagtable nobottomiftotal noborder liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

// Fields title search
// --------------------------------------------------------------------
print '<tr class="liste_titre_filter">';
// Action column
if ($conf->main_checkbox_left_column) {
	print '<td class="liste_titre center maxwidthsearch">';
	$searchpicto = $form->showFilterButtons('left');
	print $searchpicto;
	print '</td>';
}
print '<td class="liste_titre">';
print '<input class="flat" size="10" type="text" name="search_ref" value="'.$search_ref.'">';
print '</td>';
if (getDolGlobalString('DONATION_USE_THIRDPARTIES')) {
	print '<td class="liste_titre">';
	print '<input class="flat" size="10" type="text" name="search_thirdparty" value="'.$search_thirdparty.'">';
	print '</td>';
} else {
	print '<td class="liste_titre">';
	print '<input class="flat" size="10" type="text" name="search_company" value="'.$search_company.'">';
	print '</td>';
}
print '<td class="liste_titre">';
print '<input class="flat" size="10" type="text" name="search_name" value="'.$search_name.'">';
print '</td>';
print '<td class="liste_titre left">';
print '&nbsp;';
print '</td>';
if (isModEnabled('project')) {
	print '<td class="liste_titre right">';
	print '&nbsp;';
	print '</td>';
}
print '<td class="liste_titre right"><input name="search_amount" class="flat" type="text" size="8" value="'.$search_amount.'"></td>';
print '<td class="liste_titre center parentonrightofpage">';
$liststatus = array(
	Don::STATUS_DRAFT => $langs->trans("DonationStatusPromiseNotValidated"),
	Don::STATUS_VALIDATED => $langs->trans("DonationStatusPromiseValidated"),
	Don::STATUS_PAID => $langs->trans("DonationStatusPaid"),
	Don::STATUS_CANCELED => $langs->trans("Canceled")
);
// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
print $form->selectarray('search_status', $liststatus, $search_status, -4, 0, 0, '', 0, 0, 0, '', 'search_status maxwidth100 onrightofpage');
print '</td>';
// Action column
if (!$conf->main_checkbox_left_column) {
	print '<td class="liste_titre center maxwidthsearch">';
	$searchpicto = $form->showFilterButtons();
	print $searchpicto;
	print '</td>';
}
print '</tr>'."\n";

$totalarray = array();
$totalarray['nbfield'] = 0;

// Fields title label
// --------------------------------------------------------------------
print '<tr class="liste_titre">';
// Action column
if ($conf->main_checkbox_left_column) {
	print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
	$totalarray['nbfield']++;
}
print_liste_field_titre("Ref", $_SERVER["PHP_SELF"], "d.rowid", "", $param, "", $sortfield, $sortorder);
$totalarray['nbfield']++;
if (getDolGlobalString('DONATION_USE_THIRDPARTIES')) {
	print_liste_field_titre("ThirdParty", $_SERVER["PHP_SELF"], "d.fk_soc", "", $param, "", $sortfield, $sortorder);
	$totalarray['nbfield']++;
} else {
	print_liste_field_titre("Company", $_SERVER["PHP_SELF"], "d.societe", "", $param, "", $sortfield, $sortorder);
	$totalarray['nbfield']++;
}
print_liste_field_titre("Name", $_SERVER["PHP_SELF"], "d.lastname", "", $param, "", $sortfield, $sortorder);
$totalarray['nbfield']++;
print_liste_field_titre("Date", $_SERVER["PHP_SELF"], "d.datedon", "", $param, '', $sortfield, $sortorder, 'center ');
$totalarray['nbfield']++;
if (isModEnabled('project')) {
	$langs->load("projects");
	print_liste_field_titre("Project", $_SERVER["PHP_SELF"], "d.fk_projet", "", $param, "", $sortfield, $sortorder);
	$totalarray['nbfield']++;
}
print_liste_field_titre("Amount", $_SERVER["PHP_SELF"], "d.amount", "", $param, '', $sortfield, $sortorder, 'right ');
$totalarray['nbfield']++;
print_liste_field_titre("Status", $_SERVER["PHP_SELF"], "d.fk_statut", "", $param, '', $sortfield, $sortorder, 'center ');
$totalarray['nbfield']++;
// Action column
if (!$conf->main_checkbox_left_column) {
	print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
	$totalarray['nbfield']++;
}
print '</tr>'."\n";

$i = 0;
$savnbfield = $totalarray['nbfield'];
$totalarray = array();
$totalarray['nbfield'] = 0;
$imaxinloop = ($limit ? min($num, $limit) : $num);
while ($i < $imaxinloop) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break; // Should not happen
	}

	$donationstatic->setVarsFromFetchObj($obj);

	$donationstatic->id = $obj->rowid;
	$donationstatic->ref = $obj->rowid;
	$donationstatic->date = $db->jdate($obj->datedon);
	$donationstatic->status = $obj->status;
	$donationstatic->lastname = $obj->lastname;
	$donationstatic->firstname = $obj->firstname;
	$object = $donationstatic;

	$company = new Societe($db);
	$result = $company->fetch($obj->socid);


	if ($mode == 'kanban') {
		if ($i == 0) {
			print '<tr class="trkanban"><td colspan="'.$savnbfield.'">';
			print '<div class="box-flex-container kanban">';
		}
		// Output Kanban
		$donationstatic->amount = $obj->amount;

		if (!empty($obj->socid) && $company->id > 0) {
			$donationstatic->societe = $company->getNomUrl(1);
		} else {
			$donationstatic->societe = $obj->societe;
		}

		$object = $donationstatic;

		$selected = -1;
		if ($massactionbutton || $massaction) { // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
			$selected = 0;
			if (in_array($object->id, $arrayofselected)) {
				$selected = 1;
			}
		}
		print $donationstatic->getKanbanView('', array('selected' => $selected));
		if ($i == ($imaxinloop - 1)) {
			print '</div>';
			print '</td></tr>';
		}
	} else {
		// Show line of result
		$j = 0;
		print '<tr data-rowid="'.$object->id.'" class="oddeven">';

		// Action column
		if ($conf->main_checkbox_left_column) {
			print '<td class="nowrap center">';
			if ($massactionbutton || $massaction) { // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
				$selected = 0;
				if (in_array($object->id, $arrayofselected)) {
					$selected = 1;
				}
				print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Ref
		print "<td>".$donationstatic->getNomUrl(1)."</td>";

		// Company
		if (getDolGlobalString('DONATION_USE_THIRDPARTIES')) {
			if (!empty($obj->socid) && $company->id > 0) {
				print "<td>".$company->getNomUrl(1)."</td>";
			} else {
				print "<td>".((string) $obj->societe)."</td>";
			}
		} else {
			print "<td>".((string) $obj->societe)."</td>";
		}

		// Donator
		print "<td>".$donationstatic->getFullName($langs)."</td>";

		// Date donation
		print '<td class="center">'.dol_print_date($db->jdate($obj->datedon), 'day').'</td>';

		if (isModEnabled('project')) {
			print "<td>";
			if ($obj->pid) {
				$projectstatic->id = $obj->pid;
				$projectstatic->ref = $obj->ref;
				$projectstatic->id = $obj->pid;
				$projectstatic->public = $obj->public;
				$projectstatic->title = $obj->title;
				print $projectstatic->getNomUrl(1);
			} else {
				print '&nbsp;';
			}
			print "</td>\n";
		}
		print '<td class="right"><span class="amount">'.price($obj->amount).'</span></td>';

		// Status
		print '<td class="center">'.$donationstatic->LibStatut($obj->status, 5).'</td>';

		// Action column
		if (empty($conf->main_checkbox_left_column)) {
			print '<td class="nowrap center">';
			if ($massactionbutton || $massaction) { // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
				$selected = 0;
				if (in_array($object->id, $arrayofselected)) {
					$selected = 1;
				}
				print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		print '</tr>'."\n";
	}
	$i++;
}

$db->free($resql);

print '</table>'."\n";
print '</div>'."\n";

print '</form>'."\n";



llxFooter();
$db->close();
