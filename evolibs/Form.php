<?php

/**
 * Bibliotheques FORM (PHP5)
 *  
 * Copyright (c) 2004-2009 Evolix - Tous droits reserves 
 *
 * $Id: Form.php 168 2009-08-14 14:41:42Z tmartin $
 * vim: expandtab softtabstop=4 tabstop=4 shiftwidth=4 showtabline=2
 * 
 * @author Gregory Colpart <reg@evolix.fr>
 * @author Thomas Martin <tmartin@evolix.fr>
 * @author Sebastien Palma <spalma@evolix.fr>
 *  
 * Fonctions utiles pour la creation de formulaires
 *      
 * v 1.0
 */    

class FormPageController {
    private $pages = array();
    private $current_page = NULL;

    /* Affiche la page de formulaire in-line */
    public function __toString() {
        $out = '';
        $out = $this->pages[$this->current_page];
        $out .= "\n\n";
        return $out;
    }

    /* Vérifie les champs et redirige vers la dernière page valide.
       Retourne TRUE si le formulaire est entièrement valide.
       Retourne FALSE en cas d'erreur sur la page courante. */
    public function verify($set_error=TRUE) {
        $valid = TRUE;
        foreach($this->pages as $pagenum => $page) {
            if(!$page->verify($set_error)) {
                $valid = FALSE;
            }
        }
        if($valid) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function setCurrentPage($current_page) {
        $this->current_page = $current_page;
        $this->pages[$current_page]->isCurrentPage(TRUE);
    }

    public function getCurrentPage() {
        return $this->pages[$this->current_page];
    }

    public function addPage($pagenum, $obj) {
        $this->pages[$pagenum] = $obj;
    }

    /* Doit être appelé après les addField() */
    public function init() {
        foreach($this->pages as $pagenum=>$page) {
            $page->initFields();
        }
    }

    /* Retourne le numéro de page suivant, ou FALSE si on est à la fin */
    public function getNextPage() {
        $numpage = count($this->pages);
        if($this->current_page == $numpage) {
            return FALSE;
        } else {
            return $this->current_page+1;
        }
    }

    /* Retourne des infos sur les pages du formulaire, pour construire un fil
       d'ariane par exemple */
    public function getPagesList() {
        return $this->pages;
    }

    public function getPage($num = null) {
        if($num) {
            return $this->pages[$num];
        } else {
            return $this->pages[$this->current_page];
        }
    }

    /* Renvoie une collection d'objets "Field" */
    public function getFieldsList() {
        $fields = array();
        foreach($this->pages as $page) {
            foreach($page->getFieldsList() as $field) {
                $fields[$field[0]] = $field[1];
            }
        }
        return $fields;
    }

    /* Renvoie une structure de données injectable dans ldap_add() ou
       ldap_modify() */
    public function toLDAP() {

        $info = array();

        foreach($this->getFieldsList() as $name=>$obj) {
            if(is_array($obj->getValue()) && count($obj->getValue()) == 1) {
                $tmp = $obj->getValue();
                $value = $tmp[0];
            } else {
                $value = $obj->getValue();
            }

            if(!empty($value)) {
                $info[strtolower($name)] = $value;
            }
        }

        return $info;
    }

    /* Permet de définir à posteriori une liste de champs obligatoires */
    public function setMandatoryFields($list) {
        foreach($this->getFieldsList() as $fname=>$fobj) {
            if(in_array($fname, $list)) {
                $fobj->setMandatory(TRUE);
            }
        }
    }

    /* Permet d'utiliser un stockage différent de celui par défaut
     * ($_SESSION) pour tous les FormFields déjà ajouté au controlleur. Prend
     * en paramètre la référence d'un tableau
     */
    public function setSessionStorage(& $storage) {
        foreach($this->getFieldsList() as $fname=>$fobj) {
            $fobj->setSessionStorage($storage);
        }
    }
}

class FormPage {
    private $fields = array();
    private $label = NULL;
    private $is_current_page = FALSE;
    private $use_session = NULL;

    public function __construct($label, $use_session=TRUE) {
        $this->label = $label;
        $this->use_session = $use_session;
    }

    public function __toString() {
        $out = '';
        foreach($this->fields as $field) {
            $obj = $field[1];
            $out .= "$obj\n\n";
        }
        return $out;
    }

    public function getLabel() {
        return $this->label;
    }

    public function verify($set_error) {
        $num_error = 0;

        foreach($this->fields as $field) {
            $obj = $field[1];
            if($obj->verify($set_error)) {
                $this->use_session && $obj->storeSession();
            } else {
                $num_error++;
                $this->use_session && $obj->deleteSession();
            }
        }

        if($num_error > 0) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    public function addField($name, $obj) {
        $obj->setName($name);
        array_push($this->fields, array($name, $obj));
    }

    public function addFieldsArray($array) {
        foreach($array as $name=>$obj) {
            $this->addField($name, $obj);
        }
    }

    /* Petit hack : la page doit savoir si c'est elle qui est affiché, pour
       gérer le cas des checkbox */
    public function initFields($is_current_page=FALSE) {
        foreach($this->fields as $field) {
            $name = $field[0];
            $obj = $field[1];

            if((is_a($obj, 'CheckBoxInputFormField')
                        || is_a($obj, 'MultipleCheckBoxInputFormField'))
                    && $this->is_current_page
                    && !empty($_POST) && !array_key_exists($name, $_POST)) {
                $obj->setValue(0);
            } else if(is_a($obj, 'UploadFileInputFormField')
                    && $this->is_current_page
                    && array_key_exists($name, $_FILES) && $_FILES[$name]['error']==0) {
                $obj->saveTmpFile($_FILES[$name]);
            } else if(array_key_exists($name, $_POST)) {
                $obj->setValue($_POST[$name]);
            } else if($this->use_session && $obj->sessionKeyExist($name)) {
                $obj->setSessionValue($name);
            }
        }
    }

    public function isCurrentPage($is=NULL) {
        if(is_bool($is)) {
            $this->is_current_page = $is;
        }
        return $this->is_current_page;
    }


    /* Renvoie la liste des objets Fields de la page */
    public function getFieldsList() {
        return $this->fields;
    }

    /* Renvoie la liste des objets Fields de la page */
    public function getFieldsName() {
        $ret = array();
        foreach($this->fields as $field) {
            $ret[] = $field[0];
        }
        return $ret;
    }

    public function getField($name) {
        foreach($this->fields as $f) {
            if($f[0] == $name) {
                return $f[1];
            }
        }
    }
}

class FormField {
    protected $value = null;
    protected $error = null;
    protected $label = null;
    protected $name = null;
    protected $css_class = null;
    protected $read_only = null;
    protected $disabled = null;
    private $storage = NULL;

    protected function __construct($label) {
        $this->storage = & $_SESSION;
        $this->label = $label;
    }

    public function storeSession() {
        if(strlen($this->value) > 0) {
            $this->storage[$this->name] = $this->value;
        }
    }

    public function deleteSession() {
        unset($this->storage[$this->name]);
    }

    public function setSessionValue($name) {
        $this->setValue($this->storage[$name]);
    }

    public function sessionKeyExist($name) {
        #echo "$name ".$this->storage[$name]." ";
        return array_key_exists($name, $this->storage);
    }

    public function setSessionStorage(& $storage) {
        $this->storage = & $storage;
    }

    protected function verify($set_error) {
        if(empty($this->error)) {
            return TRUE;
        }
    }

    public function getError() {
        if(isset($this->value) && !$this->verify(FALSE)) {
            return $this->error;
        }
    }

    public function getErrorHTML() {
        return '<span class="form-error">'.$this->error.'</span>';
    }

    public function setError($error) {
        $this->error = $error;
    }

    public function getLabel() {
        return $this->label;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function setValue($value) {
        $this->value = $value;
    }

    public function getValue() {
        return $this->value;
    }

    public function getReadableValue() {
        return $this->getValue();
    }

    public function getLabelHTML() {
        $label = ''; 
        $label .= '<label for="'.$this->name.'">';
        $label .= $this->label.'&nbsp;';
        if ($this->mandatory) {
            if($this->verify(false)) {
                $label .=  '<span class="form-mandatory-ok">[*]</span>&nbsp;';
            } else {
                $label .=  '<span class="form-mandatory">[*]</span>&nbsp;';
            }
        }
        $label .= ': '; 
        $label .= "</label>\n";
        return $label;
    }

    public function setCSSClass($name) {
        $this->css_class = $name;
    }

    public function setReadOnly() {
        $this->read_only = true;
    }

    public function setDisabled($state = TRUE) {
        $this->disabled = $state;
    }

    public function setMandatory($bool) {
        $this->mandatory = $bool;
    }
}

/* $textsize = array( size, maxlength ) */
class TextInputFormField extends FormField {
    protected $mandatory = NULL;
    protected $textsize = NULL;

    public function __construct($label, $mandatory=TRUE, $textsize=array(20, 80)) {
        parent::__construct($label);
        $this->mandatory = $mandatory;
        $this->textsize = $textsize;
    }

    public function verify($set_error) {
        if($this->mandatory && (!strlen($this->value))) {
            if($set_error) $this->error = 'Champ obligatoire';
            return FALSE;
        }
        return TRUE;
    }

    public function getInputHTML() {
        $input = '';
        $input .= '<input type="text" id="'.$this->name.'"';
        $input .= ' name="'.$this->name.'" value="'.htmlspecialchars($this->value,ENT_QUOTES).'"';
        #$input .= sprintf(' name="%s" value="%s"', $this->name, htmlspecialchars($this->value, ENT_QUOTES));
        $input .= ' maxlength="'.$this->textsize[1].'" size="'.$this->textsize[0].'" ';
        if($this->read_only) { $input .= 'readonly="readonly="'; }
        if($this->disabled) { $input .= 'disabled="disabled="'; }
        $input .= '/>';
        return $input;
    }

    public function __toString() {
        $out = '';
        $out .= "<p>\n";
        $out .= $this->getLabelHTML();
        $out .= $this->getInputHTML();
        $out .= $this->getErrorHTML();
        $out .= "</p>\n\n";
        return $out;
    }
}

class DomainInputFormField extends FormField {
    protected $mandatory = NULL;
    protected $textsize = NULL;

    public function __construct($label, $mandatory=TRUE) {
        parent::__construct($label);
        $this->mandatory = $mandatory;
        $this->textsize = $textsize;
    }

    public function verify($set_error) {
        if($this->mandatory && (!strlen($this->value))) {
            if($set_error) $this->error = 'Champ obligatoire';
            return FALSE;
        }

        if (!preg_match("/^[a-z0-9-.]+\.[a-z]{2,}$/i", $this->value)) {
            if($set_error) $this->error = 'Ceci n\'est pas un nom de domaine';
            return FALSE;
        }

        return TRUE;
    }

    public function getInputHTML() {
        $input = '';
        $input .= '<input type="text" id="'.$this->name.'"';
        $input .= ' name="'.$this->name.'" value="'.htmlspecialchars($this->value,ENT_QUOTES).'"';
        $input .= ' maxlength="'.$this->textsize[1].'" size="'.$this->textsize[0].'" ';
        if($this->read_only) { $input .= 'readonly="readonly="'; }
        if($this->disabled) { $input .= 'disabled="disabled="'; }
        $input .= '/>';
        return $input;
    }

    public function __toString() {
        $out = '';
        $out .= "<p>\n";
        $out .= $this->getLabelHTML();
        $out .= $this->getInputHTML();
        $out .= $this->getErrorHTML();
        $out .= "</p>\n\n";
        return $out;
    }
}

class DateInputFormField extends TextInputFormField {
    public function __construct($label, $mandatory=TRUE) {
        parent::__construct($label, $mandatory, array(7, 10));
    }

    public function verify($set_error) {
        if(!parent::verify($set_error)) return FALSE;
        if(!empty($this->value) &&
                  !preg_match('#^\d{2}/\d{2}/\d{4}$#', $this->value)) {
            if($set_error) $this->error = 'Format de date non valide';
            return FALSE;
        }
        $arr_date = explode('/', $this->value);
        if(!empty($this->value) &&
                  !checkdate($arr_date[1],$arr_date[0],$arr_date[2])) {
            if($set_error) $this->error = "La date saisie n'existe pas";
            return FALSE;
        }
        return TRUE;
    }
}

class YearDateInputFormField extends TextInputFormField {
    public function __construct($label, $mandatory=TRUE) {
        parent::__construct($label, $mandatory, array(4, 4));
    }

    public function verify($set_error) {
        if(!parent::verify($set_error)) return FALSE;
        if(!empty($this->value) && (!ctype_digit($this->value) || $this->value < 1900)) {
            if($set_error) $this->error = 'Vous devez saisir une année valide';
            return FALSE;
        }
        return TRUE;
    }
}

class TelephoneInputFormField extends TextInputFormField {
    public function setValue($value) {
        $value = preg_replace('/[^\d -\.\(\)\+]/', '-', $value);
        parent::setValue($value);
    }
}

class IntegerInputFormField extends TextInputFormField {
    public function verify($set_error) {
        if(!parent::verify($set_error)) return FALSE;
        if(!empty($this->value) && !is_numeric($this->value)) {
            if($set_error) $this->error = 'Vous devez saisir un chiffre';
            return FALSE;
        }
        return TRUE;
    }
}

class FloatInputFormField extends TextInputFormField {
    public function verify($set_error) {
        if(!parent::verify($set_error)) return FALSE;
        if(!empty($this->value) && !is_numeric($this->value)) {
            if($set_error) $this->error = 'Vous devez saisir un chiffre';
            return FALSE;
        }
        return TRUE;
    }
}

class EmailInputFormField extends TextInputFormField {

    public function verify($set_error) {
        if(!parent::verify($set_error)) {
            return FALSE;
        }

        if(!empty($this->value) && !eregi('^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$',$this->value)){
            if($set_error) $this->error = 'Adresse email invalide';
            return FALSE;
        }

        return TRUE;
    }
}

class TextareaFormField extends FormField {
    protected $mandatory = NULL;
    protected $cols = NULL;
    protected $rows = NULL;

    public function __construct($label, $mandatory=TRUE, $cols=45, $rows=5) {
        parent::__construct($label);
        $this->mandatory = $mandatory;
        $this->rows = $rows;
        $this->cols = $cols;
    }

    public function verify($set_error) {
        if($this->mandatory && empty($this->value)) {
            if($set_error) $this->error = 'Champ obligatoire';
            return FALSE;
        }

        return TRUE;
    }

    public function getInputHTML() {
        $input = '';
        $input .= '<textarea cols="'.$this->cols.'" rows="'.$this->rows.'" id="'.$this->name.'"';
        $input .= ' name="'.$this->name.'">'.htmlspecialchars($this->value,ENT_QUOTES).'</textarea>';
        return $input;
    }

    public function __toString() {
        $out = '';
        $out .= "<p class='form-textarea'>\n";
        $out .= $this->getLabelHTML();
        $out .= $this->getInputHTML();
        $out .= $this->getErrorHTML();
        $out .= "</p>\n\n";
        return $out;
    }
}


class PasswordInputFormField extends FormField {
    protected $mandatory = NULL;

    public function __construct($label, $mandatory=TRUE) {
        parent::__construct($label);
        $this->mandatory = $mandatory;
    }

    public function verify($set_error) {
        if(empty($this->value)) {
            if($this->mandatory) {
                if($set_error) $this->error = 'Champ obligatoire';
                return FALSE;
            } else {
                return TRUE;
            }
        }

        if(strlen($this->value) < 8) {
            if($set_error) $this->error = '8 caracteres minimum';
            return FALSE;
        }

        #if(preg_match('#.*[A-Z]+.*$#',$this->value)==0){
        #            if($set_error) $this->error = 'Votre mot de passe doit contenir au moins une majuscule';
        #            return FALSE;
        #        }

        if(preg_match('#.*[0-9]+.*#',$this->value)==0){
            if($set_error) $this->error = 'Votre mot de passe doit contenir au moins un chiffre';
            return FALSE;
        }

        return TRUE;
    }


    public function getInputHTML() {
        $input = sprintf('<input type="password" id="%s" name="%s" value="%s"',
                         $this->name, $this->name,
                         htmlspecialchars($this->value, ENT_QUOTES));
        if($this->read_only) $input .= ' readonly="readonly"';
        if($this->disabled) { $input .= 'disabled="disabled="'; }
        $input .= '/>';
        return $input;
    }

    public function __toString() {
        $out = '';
        $out .= "<p>\n";
        $out .= $this->getLabelHTML(); 
        $out .= $this->getInputHTML(); 
        $out .= $this->getErrorHTML(); 
        $out .= "</p>\n\n";
        return $out;
    }
}


class CheckBoxInputFormField extends FormField {
    protected $mandatory = NULL;

    public function __construct($label, $mandatory=TRUE) {
        parent::__construct($label);
        $this->mandatory = $mandatory;
    }

    public function verify($set_error) {
        if(!parent::verify($set_error)) return FALSE;
        if($this->mandatory && empty($this->value)) {
            if($set_error) $this->error = 'Champ obligatoire';
            return FALSE;
        }

        return TRUE;
    }


    public function getInputHTML() {
        $input = sprintf('<input type="checkbox" id="%s" name="%s"',
                         $this->name, $this->name);
        if($this->value) $input .= ' checked="checked"';
        if($this->disabled) $input .= ' disabled="disabled"';
        $input .= ' value="1" />';
        return $input;
    }

    public function __toString() {
        $out = '';
        $out .= "<p>\n";
        $out .= $this->getLabelHTML();
        $out .= $this->getInputHTML(); 
        $out .= $this->getErrorHTML(); 
        $out .= "</p>\n\n";
        return $out;
    }
}

class MultipleCheckBoxInputFormField extends FormField {
    protected $mandatory = NULL;
    protected $list = array();

    public function __construct($label, $mandatory=TRUE, $list) {
        parent::__construct($label);
        $this->mandatory = $mandatory;
        $this->list = $list;
    }

    public function verify($set_error) {
        if($this->mandatory && empty($this->value)) {
            if($set_error) $this->error = 'Vous devez faire au moins un choix';
            return FALSE;
        }

        return TRUE;
    }

    public function getInputHTML() {
        $input = '';
        $n = 0;
        foreach ($this->list as $value => $label) {
            $input .= '<input type="checkbox" name="'.$this->name.'[]" value="'.htmlspecialchars($value,ENT_QUOTES).'"';
            if (is_array($this->value) && in_array($value, $this->value)) {
                $input.=' checked="checked"'; 
            }
            if($this->read_only) { $input .= 'readonly="readonly="'; }
            $input .= "/>$label";
            $n++;
            if($n < count($this->list)) $input .= "<br/>\n";
        }
        return $input;
    }

    public function getErrorHTML() {
        if($this->error) {
            return "<br/><span class='form-error form-incolumn-error'>{$this->error}</span>";
        } else {
            return "";
        }
    }

    public function __toString() {
        $out = '';
        $out .= "<p>\n";
        $out .= $this->getLabelHTML();
        $out .= $this->getInputHTML();
        $out .= $this->getErrorHTML(); 
        $out .= "</p>\n\n";
        return $out;
    }

    public function getValue() {
        $out = array();
        foreach($this->value as $index) {
#$out[] = $this->list[$index];
            $out[] = $index;
        }
        return $out;
    }

    public function getReadableValue() {
        $n = 1;
        $out = '';
        foreach($this->value as $index) {
            $out .= $this->list[$index];
            if($n < count($this->value)) $out .= ', ';
            $n++;
        }
        return $out;
    }

    public function setValue($value) {
        if(!is_array($value)) {
            $this->value = array($value);
        } else {
            parent::setValue($value);
        }
    }
}


class SelectFormField extends FormField {
    protected $mandatory = NULL;
    protected $list = array();

    public function __construct($label, $mandatory=TRUE, $list) {
        parent::__construct($label);
        $this->mandatory = $mandatory;
        $this->list = $list;
    }

    public function verify($set_error) {
        if($this->mandatory && empty($this->value)) {
            if($set_error) $this->error = 'Champ obligatoire';
            return FALSE;
        }

        return TRUE;
    }


    public function getInputHTML() {
        $input = '';
        $input .= '<select id="'.$this->name.'" name="'.$this->name.'">'."\n";
        $input .= '  <option value="">-- Choisissez --</option>'."\n";
        foreach ($this->list as $value => $label) {
            $input .= '  <option value="'.htmlspecialchars($value,ENT_QUOTES).'"';
            if ($this->value == $value) $input.=' selected="selected"';
            $input .= '>'.$label.'</option>'."\n";
        }
        $input .= "</select>\n";
        return $input;
    }

    public function __toString() {
        $out = '';
        $out .= "<p>\n";
        $out .= $this->getLabelHTML();
        $out .= $this->getInputHTML();
        $out .= $this->getErrorHTML(); 
        $out .= "</p>\n\n";
        return $out;
    }

    public function getValue() {
        return $this->value;
    }

    public function getReadableValue() {
        if(array_key_exists($this->value, $this->list)) {
            return $this->list[$this->value];
        } else {
            return null;
        }
    }
}

class RadioFormField extends FormField {
    protected $mandatory = NULL;
    protected $list = array();

    public function __construct($label, $mandatory=TRUE, $list, $default = null) {
        parent::__construct($label);
        $this->mandatory = $mandatory;
        $this->list = $list;
        if($default) {
            $this->value = $default;
        }
    }

    public function verify($set_error) {
        if($this->mandatory && empty($this->value)) {
            if($set_error) $this->error = 'Champ obligatoire';
            return FALSE;
        }

        return TRUE;
    }

    public function getInputHTML() {
        $input = '';
        $input .= '<span class="form-radio">';
        foreach ($this->list as $value => $label) {
            $input .= '<input type="radio" name="'.$this->name.'" value="'.htmlspecialchars($value,ENT_QUOTES).'"';
            if ($this->value == $value) $input.=' checked="checked"';
            $input .= "/>$label<br/>";
        }
        $input .= '</span>';
        return $input;
    }    

    public function getErrorHTML() {
        return "<span class='form-error form-incolumn-error'>{$this->error}</span>";
    }

    public function __toString() {
        $out = '';
        $out .= "<p>\n";
        $out .= $this->getLabelHTML();
        $out .= $this->getInputHTML();
        $out .= $this->getErrorHTML(); 
        $out .= "</p>\n\n";
        return $out;
    }

    public function getValue() {
        return $this->value;
    }

    public function getReadableValue() {
        if(array_key_exists($this->value, $this->list)) {
            return $this->list[$this->value];
        } else {
            return null;
        }
    }
}

class ButtonInputFormField extends FormField {
    protected $mandatory = NULL;
    protected $event = NULL;
    protected $action = NULL;

    public function __construct($label,$mandatory=FALSE, $event, $action) {
        parent::__construct($label);
        $this->mandatory = $mandatory;
        $this->event = $event;
        $this->action = $action;
    }

    public function verify($set_error) {
        return TRUE;
    }


    public function getInputHTML() {
        $input = '';
        $input .= '<input type="button" name="'.$this->name.'" ';
        if (!empty($this->event) && !empty($this->action)) {
            $input .= $this->event.'='.$this->action.' ';
        }
        $input .= 'value="'.htmlspecialchars($this->label,ENT_QUOTES).'" />';
        return $input;
    }

    public function __toString() {
        $out = '';
        $out .= "<p>\n";
        $out .= $this->getInputHTML(); 
        $out .= $this->getErrorHTML(); 
        $out .= "</p>\n\n";
        return $out;
    }
}


class SubmitInputFormField extends FormField {
    protected $mandatory = NULL;
    protected $event = NULL;
    protected $action = NULL;

    public function __construct($label,$mandatory=FALSE) {
        parent::__construct($label);
        $this->mandatory = $mandatory;
    }

    public function verify($set_error) {
        return TRUE;
    }


    public function getInputHTML() {
        $input = '';
        $input .= '<input type="submit" name="'.$this->name.'" ';
        $input .= 'class="'.$this->css_class.'" value="'.htmlspecialchars($this->label,ENT_QUOTES).'" />';
        return $input;
    }

    public function __toString() {
        $out = '';
        $out .= "<p>\n";
        $out .= $this->getInputHTML(); 
        $out .= $this->getErrorHTML(); 
        $out .= "</p>\n\n";
        return $out;
    }
}

class UploadFileInputFormField extends FormField {
    protected $mandatory = NULL;
    protected $delete_url = NULL;

    public function __construct($label,$mandatory=FALSE) {
        parent::__construct($label);
        $this->mandatory = $mandatory;
    }

    public function verify($set_error) {
        return TRUE;
    }

    public function setDeleteUrl($url) {
        $this->delete_url = $url;
    }


    public function deleteUploadedFile() {
        if(!is_array($this->value)) {
            return "Value is not an array";
        }

        if(file_exists('/tmp/'.$this->value['tempname'])) {
            unlink('/tmp/'.$this->value['tempname']);
        }

        $this->value = NULL;

        return true;
    }  

    public function saveTmpFile($upload_array) {
        $this->value['filename'] = $upload_array['name'];
        $this->value['file_ext'] = end(explode(".", $this->value['filename'])); 
        $this->value['tempname'] = 'tmp_'.time().'.'.$this->value['file_ext'];
        $up_filename = $upload_array['tmp_name'];

        if(!move_uploaded_file($up_filename, '/tmp/'.$this->value['tempname'])) {
            $this->error = $this->value['error'];
        } 
    }

    public function moveTmpFile($filename, $dest_path) {
        $this->value['filename'] = $filename.'.'.$this->value['file_ext'];

        if(!file_exists('/tmp/'.$this->value['tempname'])) {
            $this->error = "Impossible de trouver le fichier /tmp/{$this->value['tempname']}";
            return "";
        }

        if((rename('/tmp/'.$this->value['tempname'],$dest_path.$this->value['filename']))===FALSE) {
            $this->error = "Impossible de déplacer le fichier vers $dest_path{$this->value['filename']}";
            return "";
        }
        return $dest_path.$this->value['filename'];

    }

    public function getInputHTML($delete_url='') {
        $input = '';
        $input .= '<input type="file" name="'.$this->name.'" id="'.$this->name.'" />';
        if(is_array($this->value)) {
            $filename = $this->value['filename'];
        } elseif (!empty($this->value)){
            $filename = $this->value;
        }

        if (!empty($filename)) {
            $input .= "<br/><div class='form-comment'>Fichier uploadé : ";
            $input .= "<a href=\"/inscription/viewcv/$filename\">$filename</a>";
            if(!empty($this->delete_url)) {
                $input .= '&nbsp;<a href="'.$this->delete_url.'" target="_blank" style="text-decoration:none"><font color="red"><b>X</b></font></a>';
            }
            $input .= "</div>";
        }

        return $input;
    }

    public function __toString() {
        $out = '';
        $out .= "<p>\n";
        $out .= $this->getLabelHTML();
        $out .= $this->getInputHTML();
        $out .= $this->getErrorHTML(); 
        $out .= "</p>\n\n";
        return $out;
    }

    public function getReadableValue() {
        return $this->value['filename'];
    }
}

?>
