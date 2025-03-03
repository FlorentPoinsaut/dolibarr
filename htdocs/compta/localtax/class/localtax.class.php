<?php
/* Copyright (C) 2011-2014	Juanjo Menent	<jmenent@2byte.es>
 * Copyright (C) 2024       Frédéric France             <frederic.france@free.fr>
 * Copyright (C) 2024-2025	MDW							<mdeweerd@users.noreply.github.com>
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
 *      \file       htdocs/compta/localtax/class/localtax.class.php
 *      \ingroup    tax
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';


/**
 *	Class to manage local tax
 */
class Localtax extends CommonObject
{
	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'localtax';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'localtax';

	/**
	 * @var string String with name of icon for myobject. Must be the part after the 'object_' into object_myobject.png
	 */
	public $picto = 'payment';

	/**
	 * @var int
	 */
	public $ltt;

	/**
	 * @var int|string
	 */
	public $datep;
	/**
	 * @var int|string
	 */
	public $datev;
	/**
	 * @var string
	 */
	public $amount;

	/**
	 * @var int
	 */
	public $accountid;

	/**
	 * @var string
	 */
	public $fk_type;

	/**
	 * @var string
	 */
	public $paymenttype;

	/**
	 * @var int
	 */
	public $rappro;


	/**
	 * @var string local tax
	 */
	public $label;

	/**
	 * @var int ID
	 */
	public $fk_bank;

	/**
	 * @var int ID
	 */
	public $fk_user_creat;

	/**
	 * @var int ID
	 */
	public $fk_user_modif;

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 *  Create in database
	 *
	 *  @param      User	$user       User that create
	 *  @return     int      			Return integer <0 if KO, >0 if OK
	 */
	public function create($user)
	{
		global $conf, $langs;

		$error = 0;

		// Clean parameters
		$this->amount = trim($this->amount);
		$this->label = trim($this->label);
		$this->note = trim($this->note);

		// Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."localtax(";
		$sql .= "localtaxtype,";
		$sql .= "tms,";
		$sql .= "datep,";
		$sql .= "datev,";
		$sql .= "amount,";
		$sql .= "label,";
		$sql .= "note,";
		$sql .= "fk_bank,";
		$sql .= "fk_user_creat,";
		$sql .= "fk_user_modif";
		$sql .= ") VALUES (";
		$sql .= " ".((int) $this->ltt).",";
		$sql .= " '".$this->db->idate($this->tms)."',";
		$sql .= " '".$this->db->idate($this->datep)."',";
		$sql .= " '".$this->db->idate($this->datev)."',";
		$sql .= " '".$this->db->escape($this->amount)."',";
		$sql .= " '".$this->db->escape($this->label)."',";
		$sql .= " '".$this->db->escape($this->note)."',";
		$sql .= " ".($this->fk_bank <= 0 ? "NULL" : (int) $this->fk_bank).",";
		$sql .= " ".((int) $this->fk_user_creat).",";
		$sql .= " ".((int) $this->fk_user_modif);
		$sql .= ")";

		dol_syslog(get_class($this)."::create", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."localtax");

			// Call trigger
			$result = $this->call_trigger('LOCALTAX_CREATE', $user);
			if ($result < 0) {
				$error++;
			}
			// End call triggers

			if (!$error) {
				$this->db->commit();
				return $this->id;
			} else {
				$this->db->rollback();
				return -1;
			}
		} else {
			$this->error = "Error ".$this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *	Update database
	 *
	 *	@param		User	$user        	User that modify
	 *	@param		int		$notrigger		0=no, 1=yes (no update trigger)
	 *	@return		int						Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		global $conf, $langs;

		$error = 0;

		// Clean parameters
		$this->amount = trim($this->amount);
		$this->label = trim($this->label);
		$this->note = trim($this->note);

		$this->db->begin();

		// Update request
		$sql = "UPDATE ".MAIN_DB_PREFIX."localtax SET";
		$sql .= " localtaxtype=".((int) $this->ltt).",";
		$sql .= " tms='".$this->db->idate($this->tms)."',";
		$sql .= " datep='".$this->db->idate($this->datep)."',";
		$sql .= " datev='".$this->db->idate($this->datev)."',";
		$sql .= " amount=".price2num($this->amount).",";
		$sql .= " label='".$this->db->escape($this->label)."',";
		$sql .= " note='".$this->db->escape($this->note)."',";
		$sql .= " fk_bank=".(int) $this->fk_bank.",";
		$sql .= " fk_user_creat=".(int) $this->fk_user_creat.",";
		$sql .= " fk_user_modif=".(int) $this->fk_user_modif;
		$sql .= " WHERE rowid=".((int) $this->id);

		dol_syslog(get_class($this)."::update", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = "Error ".$this->db->lasterror();
			$error++;
		}

		if (!$error && !$notrigger) {
			// Call trigger
			$result = $this->call_trigger('LOCALTAX_MODIFY', $user);
			if ($result < 0) {
				$error++;
			}
			// End call triggers
		}

		if (!$error) {
			$this->db->commit();
			return 1;
		} else {
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *	Load object in memory from database
	 *
	 *	@param		int		$id		Object id
	 *	@return		int				Return integer <0 if KO, >0 if OK
	 */
	public function fetch($id)
	{
		$sql = "SELECT";
		$sql .= " t.rowid,";
		$sql .= " t.localtaxtype,";
		$sql .= " t.tms,";
		$sql .= " t.datep,";
		$sql .= " t.datev,";
		$sql .= " t.amount,";
		$sql .= " t.label,";
		$sql .= " t.note as note_private,";
		$sql .= " t.fk_bank,";
		$sql .= " t.fk_user_creat,";
		$sql .= " t.fk_user_modif,";
		$sql .= " b.fk_account,";
		$sql .= " b.fk_type,";
		$sql .= " b.rappro";
		$sql .= " FROM ".MAIN_DB_PREFIX."localtax as t";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."bank as b ON t.fk_bank = b.rowid";
		$sql .= " WHERE t.rowid = ".((int) $id);

		dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id    = $obj->rowid;
				$this->ref   = $obj->rowid;
				$this->ltt   = $obj->localtaxtype;
				$this->tms   = $this->db->jdate($obj->tms);
				$this->datep = $this->db->jdate($obj->datep);
				$this->datev = $this->db->jdate($obj->datev);
				$this->amount = $obj->amount;
				$this->label = $obj->label;
				$this->note  = $obj->note_private;
				$this->note_private  = $obj->note_private;
				$this->fk_bank = $obj->fk_bank;
				$this->fk_user_creat = $obj->fk_user_creat;
				$this->fk_user_modif = $obj->fk_user_modif;
				$this->fk_account = $obj->fk_account;
				$this->fk_type = $obj->fk_type;
				$this->rappro  = $obj->rappro;
			}
			$this->db->free($resql);

			return 1;
		} else {
			$this->error = "Error ".$this->db->lasterror();
			return -1;
		}
	}


	/**
	 *	Delete object in database
	 *
	 *	@param		User	$user		User that delete
	 *	@return		int					Return integer <0 if KO, >0 if OK
	 */
	public function delete($user)
	{
		// Call trigger
		$result = $this->call_trigger('LOCALTAX_DELETE', $user);
		if ($result < 0) {
			return -1;
		}
		// End call triggers

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."localtax";
		$sql .= " WHERE rowid=".((int) $this->id);

		dol_syslog(get_class($this)."::delete", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = "Error ".$this->db->lasterror();
			return -1;
		}

		return 1;
	}


	/**
	 *  Initialise an instance with random values.
	 *  Used to build previews or test instances.
	 *	id must be 0 if object instance is a specimen.
	 *
	 *  @return	int
	 */
	public function initAsSpecimen()
	{
		global $user;

		$this->id = 0;

		$this->tms = dol_now();
		$this->ltt = 0;
		$this->datep = '';
		$this->datev = '';
		$this->amount = '';
		$this->label = '';
		$this->note = '';
		$this->fk_bank = 0;
		$this->fk_user_creat = $user->id;
		$this->fk_user_modif = $user->id;

		return 1;
	}


	/**
	 *	Hum la function s'appelle 'Solde' elle doit a mon avis calcluer le solde de localtax, non ?
	 *
	 *	@param	int		$year		Year
	 *	@return	int					???
	 */
	public function solde($year = 0)
	{
		$reglee = $this->localtax_sum_reglee($year);

		$payee = $this->localtax_sum_payee($year);
		$collectee = $this->localtax_sum_collectee($year);

		$solde = $reglee - ($collectee - $payee);

		return $solde;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Total de la localtax des factures emises par la societe.
	 *
	 *	@param	int		$year		Year
	 *	@return	int					???
	 */
	public function localtax_sum_collectee($year = 0)
	{
		// phpcs:enable
		$sql = "SELECT sum(f.localtax) as amount";
		$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
		$sql .= " WHERE f.paye = 1";
		if ($year) {
			$sql .= " AND f.datef BETWEEN '".$this->db->idate(dol_get_first_day($year, 1, 'gmt'))."' AND '".$this->db->idate(dol_get_last_day($year, 1, 'gmt'))."'";
		}

		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);
				$ret = $obj->amount;
				$this->db->free($result);
				return $ret;
			} else {
				$this->db->free($result);
				return 0;
			}
		} else {
			print $this->db->lasterror();
			return -1;
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Total of localtax paid in invoice
	 *
	 *	@param	int		$year		Year
	 *	@return	int					???
	 */
	public function localtax_sum_payee($year = 0)
	{
		// phpcs:enable

		$sql = "SELECT sum(f.total_localtax) as total_localtax";
		$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn as f";
		if ($year) {
			$sql .= " WHERE f.datef BETWEEN '".$this->db->idate(dol_get_first_day($year, 1, 'gmt'))."' AND '".$this->db->idate(dol_get_last_day($year, 1, 'gmt'))."'";
		}

		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);
				$ret = $obj->total_localtax;
				$this->db->free($result);
				return $ret;
			} else {
				$this->db->free($result);
				return 0;
			}
		} else {
			print $this->db->lasterror();
			return -1;
		}
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Total of localtax paid
	 *
	 *	@param	int		$year		Year
	 *	@return	int					???
	 */
	public function localtax_sum_reglee($year = 0)
	{
		// phpcs:enable

		$sql = "SELECT sum(f.amount) as amount";
		$sql .= " FROM ".MAIN_DB_PREFIX."localtax as f";
		if ($year) {
			$sql .= " WHERE f.datev BETWEEN '".$this->db->idate(dol_get_first_day($year, 1, 'gmt'))."' AND '".$this->db->idate(dol_get_last_day($year, 1, 'gmt'))."'";
		}

		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);
				$ret = $obj->amount;
				$this->db->free($result);
				return $ret;
			} else {
				$this->db->free($result);
				return 0;
			}
		} else {
			print $this->db->lasterror();
			return -1;
		}
	}


	/**
	 *	Add a payment of localtax
	 *
	 *	@param		User	$user		Object user that insert
	 *	@return		int					Return integer <0 if KO, rowid in localtax table if OK
	 */
	public function addPayment($user)
	{
		global $conf, $langs;

		$this->db->begin();

		// Check parameters
		$this->amount = price2num($this->amount);
		if (!$this->label) {
			$this->error = $langs->trans("ErrorFieldRequired", $langs->transnoentities("Label"));
			return -3;
		}
		if ($this->amount <= 0) {
			$this->error = $langs->trans("ErrorFieldRequired", $langs->transnoentities("Amount"));
			return -4;
		}
		if (isModEnabled("bank") && (empty($this->accountid) || $this->accountid <= 0)) {
			$this->error = $langs->trans("ErrorFieldRequired", $langs->transnoentities("BankAccount"));
			return -5;
		}
		if (isModEnabled("bank") && (empty($this->paymenttype) || $this->paymenttype <= 0)) {
			$this->error = $langs->trans("ErrorFieldRequired", $langs->transnoentities("PaymentMode"));
			return -5;
		}

		// Insertion dans table des paiement localtax
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."localtax (localtaxtype, datep, datev, amount";
		if ($this->note) {
			$sql .= ", note";
		}
		if ($this->label) {
			$sql .= ", label";
		}
		$sql .= ", fk_user_creat, fk_bank";
		$sql .= ") ";
		$sql .= " VALUES (".$this->ltt.", '".$this->db->idate($this->datep)."',";
		$sql .= "'".$this->db->idate($this->datev)."',".$this->amount;
		if ($this->note) {
			$sql .= ", '".$this->db->escape($this->note)."'";
		}
		if ($this->label) {
			$sql .= ", '".$this->db->escape($this->label)."'";
		}
		$sql .= ", ".((int) $user->id).", NULL";
		$sql .= ")";

		dol_syslog(get_class($this)."::addPayment", LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."localtax"); // TODO devrait s'appeler paiementlocaltax
			if ($this->id > 0) {
				$ok = 1;
				if (isModEnabled("bank")) {
					// Insertion dans llx_bank
					require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

					$acc = new Account($this->db);
					$result = $acc->fetch($this->accountid);
					if ($result <= 0) {
						dol_print_error($this->db);
					}

					$bank_line_id = $acc->addline($this->datep, $this->paymenttype, $this->label, -abs((float) $this->amount), '', 0, $user);

					// Update fk_bank into llx_localtax so we know the line of localtax used to generate the bank entry.
					if ($bank_line_id > 0) {
						$this->update_fk_bank($bank_line_id);
					} else {
						$this->error = $acc->error;
						$ok = 0;
					}

					// Update the links
					$result = $acc->add_url_line($bank_line_id, $this->id, DOL_URL_ROOT.'/compta/localtax/card.php?id=', "(VATPayment)", "payment_vat");
					if ($result < 0) {
						$this->error = $acc->error;
						$ok = 0;
					}
				}

				if ($ok) {
					$this->db->commit();
					return $this->id;
				} else {
					$this->db->rollback();
					return -3;
				}
			} else {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -2;
			}
		} else {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Update the link between localtax payment and the line into llx_bank
	 *
	 *	@param		int		$id		Id bank account
	 *	@return		int				Return integer <0 if KO, >0 if OK
	 */
	public function update_fk_bank($id)
	{
		// phpcs:enable
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'localtax SET fk_bank = '.((int) $id);
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$result = $this->db->query($sql);
		if ($result) {
			return 1;
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}


	/**
	 *	Returns clickable name
	 *
	 *	@param		int		$withpicto		0=Link, 1=Picto into link, 2=Picto
	 *	@param		string	$option			What the link points to
	 *	@return		string					Chaine avec URL
	 */
	public function getNomUrl($withpicto = 0, $option = '')
	{
		global $langs;

		$result = '';
		$label = $langs->trans("ShowVatPayment").': '.$this->ref;

		$link = '<a href="'.DOL_URL_ROOT.'/compta/localtax/card.php?id='.$this->id.'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
		$linkend = '</a>';

		$picto = 'payment';

		if ($withpicto) {
			$result .= ($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
		}
		if ($withpicto && $withpicto != 2) {
			$result .= ' ';
		}
		if ($withpicto != 2) {
			$result .= $link.$this->ref.$linkend;
		}
		return $result;
	}

	/**
	 *  Return the label of the status
	 *
	 *  @param  int		$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string 			       Label of status
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->statut, $mode);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return the label of a given status
	 *
	 *  @param	int		$status        Id status
	 *  @param  int		$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return string 			       Label of status
	 */
	public function LibStatut($status, $mode = 0)
	{
		// phpcs:enable
		//global $langs;

		return '';
	}

	/**
	 *	Return clickable link of object (with eventually picto)
	 *
	 *	@param      string	    			$option                 Where point the link (0=> main card, 1,2 => shipment, 'nolink'=>No link)
	 *  @param		?array<string,mixed>	$arraydata				Array of data
	 *  @return		string											HTML Code for Kanban thumb.
	 */
	public function getKanbanView($option = '', $arraydata = null)
	{
		global $langs;

		$selected = (empty($arraydata['selected']) ? 0 : $arraydata['selected']);

		$return = '<div class="box-flex-item box-flex-grow-zero">';
		$return .= '<div class="info-box info-box-sm">';
		$return .= '<span class="info-box-icon bg-infobox-action">';
		$return .= img_picto('', $this->picto);
		$return .= '</span>';
		$return .= '<div class="info-box-content">';
		$return .= '<span class="info-box-ref inline-block tdoverflowmax150 valignmiddle">'.(method_exists($this, 'getNomUrl') ? $this->getNomUrl() : $this->ref).'</span>';
		if ($selected >= 0) {
			$return .= '<input id="cb'.$this->id.'" class="flat checkforselect fright" type="checkbox" name="toselect[]" value="'.$this->id.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		if (property_exists($this, 'label')) {
			$return .= ' | <span class="info-box-label">'.$this->label.'</span>';
		}
		if (property_exists($this, 'datev')) {
			$return .= '<br><span class="opacitymedium">'.$langs->trans("DateEnd").'</span> : <span class="info-box-label">'.dol_print_date($this->db->jdate($this->datev), 'day').'</span>';
		}
		if (property_exists($this, 'datep')) {
			$return .= '<br><span class="opacitymedium">'.$langs->trans("DatePayment", '', '', '', '', 5).'</span> : <span class="info-box-label">'.dol_print_date($this->db->jdate($this->datep), 'day').'</span>';
		}
		if (property_exists($this, 'amount')) {
			$return .= '<br><span class="opacitymedium">'.$langs->trans("Amount").'</span> : <span class="info-box-label amount">'.price($this->amount).'</span>';
		}
		$return .= '</div>';
		$return .= '</div>';
		$return .= '</div>';
		return $return;
	}
}
