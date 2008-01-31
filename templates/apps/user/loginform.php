<?php
// instantiate user model
$User = new User;
// get current request
$request = PRequest::get()->request;

$loginText = array();
$i18n = new MOD_i18n('apps/user/login.php');
$loginText = $i18n->getText('loginText');
$words = new MOD_words();

/*
 * LOGIN FORM
 */

if (!APP_User::loggedIn()) {
    // retrieve the callback ID
    $callbackId = $User->loginProcess();
    // get the saved post vars
    $vars =& PPostHandler::getVars($callbackId);
    if (isset($vars['errors']) && is_array($vars['errors']) && in_array('not_logged_in', $vars['errors'])) {
?>
 <p class="error"><?php echo $loginText['not_logged_in']; ?></p>
<?php
    }
?>

<div class="info">
  <h3><?php echo $words->get('Login'); ?></h3>
  <form method="post" action="<?php
// action is current request 
echo implode('/', $request); 
?>">
    <p>
      <label for="login-u"><?php echo $words->get('Username'); ?></label>
      <input type="text" id="login-u" name="u" <?php 
// the username may be set
echo isset($vars['u']) ? 'value="'.htmlentities($vars['u'], ENT_COMPAT, 'utf-8').'" ' : ''; 
?>/>
    </p>
    <p>
      <label for="login-p"><?php echo $words->get('Password'); ?></label>
      <input type="password" id="login-p" name="p" />
    </p>
    <p>
      <input type="submit" value="<?php echo $words->get('login'); ?>" class="button"/>
      <input type="hidden" name="<?php
// IMPORTANT: callback ID for post data 
echo $callbackId; ?>" value="1"/>
    </p>
    <p><?php echo $words->getFormatted('IndexPageWord18','<a href="/bw/lostpassword.php">','</a>');?></p>
    <h3><?php echo $words->getFormatted('SignupNow'); ?></h3>
    <p><?php echo $words->getFormatted('IndexPageWord17','<a href="/bw/signup.php">','</a>'); ?></p>
    
</form>
<script type="text/javascript">document.getElementById("login-u").focus();</script>
</div>
<!-- END -->
<?php
// and remove unused vars
PPostHandler::clearVars($callbackId);
} else {
/*
 * STATUS AND LOGOUT FORM
 */
$c = $User->logoutProcess();
$currUser = APP_User::get();
$navText = $i18n->getText('navText');
$countrycode = APP_User::countryCode($currUser->getHandle());
$words = new MOD_words();
?>
<div class="floatbox">
<p><?php echo $words->getFormatted('UserLoggedInAs'); ?> <br />
    <a href="user/<?php echo $currUser->getHandle(); ?>">
    <?=$currUser->getHandle()?></a>
<?php
if ($countrycode) {
?>        
    <a href="country/<?php echo $countrycode; ?>"><img src="images/icons/flags/<?php echo strtolower($countrycode); ?>.png" alt="" /></a>
<?php
}
?>
    
</p>

<form method="post" action="<?php
/* action is current request */
echo implode('/', $request); 
?>" id="user-leftnav">
  <!--  <ul>
        
        <li><a href="user/settings"><?php echo $navText['settings']; ?></a></li>
       
    </ul> -->
<p>
    <input type="submit" value="<?php echo $loginText['logout']; ?>"/>
    <input type="hidden" name="<?php echo $c; ?>" value="1"/>
</p>
</form>
</div>
<?php 
}
?>
