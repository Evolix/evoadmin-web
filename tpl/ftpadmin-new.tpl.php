

<form name="form-add" id="form-add" action="/ftpadmin/add/" method="POST">
    <fieldset>
        <legend>Ajouter un compte FTP</legend>

        <?php
            if (array_key_exists('error', $_SESSION) && is_numeric($_SESSION['error']) && $_SESSION['error']!=3) {
        ?>
        <p class="form-error">
            <?php print $errors[$_SESSION['error']]; ?></td>
        </p>
        <?php } ?>

        <?php
            if(is_superadmin()) {
                print '<dl>';
                print '<dt><label for="owner">Propriétaire : </label></dt>';
                print '<dd><select id="owner" name="owner">';
                foreach($owner_list as $owner_name) {
                    printf('<option>%s</option>', $owner_name);
                }
                print '</select></dd>';
                print '</dl>';
            }
        ?>
        
        <dl>
            <dt><label for="login">Utilisateur : </label></dt>
            <dd><input type="text" name="login"
                id="login" onblur="fill_directory_field(this.value);"
                value="<?php @print $_SESSION['form']['login'];?>" <?php print $readonly ?>/></dd>
        </dl>
        
        <dl>
            <dt><label for="passwd">Mot de passe : </label></dt>
            <dd><input type="password" name="passwd" id="passwd" value="<?php @print $_SESSION['form']['passw']; ?>" <?php print $readonly ?>/></dd>
        </dl>
        
        <dl>
            <dt><label for="passwd_c">Confirmation du mot de passe : </label></dt>
            <dd><input type="password" name="passwd_c" id="passwd_c" value="<?php @print $_SESSION['form']['pass2']; ?>" <?php print $readonly ?>/></dd>
        </dl>
        
        <dl>
            <dt><label for="path">Répertoire : </label></dt>
            <dd><input type="text" name="path" id="path" value="<?php @print $_SESSION['form']['dossier']; ?>" <?php print $readonly ?>/></dd>
        </dl>
        
        <?php
            if (array_key_exists('error', $_SESSION) && $_SESSION['error']==3) {
        ?>
        <dl>
            <dt><label><?php print $errors[3]; ?></label></dt>
            <dd>
                <label>
                    <input type="radio" name="confirm" value="1" checked="checked"/>
                    Oui
                </label>
                <label>
                    <input type="radio" name="confirm" value="0" />
                    Non
                </label>
            </dd>
        </dl>
        <?php } ?>

        <dl>
            <dt>&nbsp;</dt>
            <dd><input type="submit" value="Exécuter" name="submit_button"
                       onclick="return check_form_ftp_add()" /></dd>
        </dl>
    </fieldset>
</form>
