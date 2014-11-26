<?php
	function demagicalize_string ($string)
	{
		if (get_magic_quotes_gpc ())
			$string = stripslashes ($string);

		return $string;
	}

	$ignore_no_login=true;
	$session_not_close=true;

	chdir("../noframes");
	require_once("common.php");

	if($_SERVER['SERVER_NAME'] != 'chat.qed-verein.de')
		die("grml");

	if(empty($_SESSION['userid']))
		redirect(urlLogin(chatOptions()));

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">

<html>

<head>
<meta name="robots" content="noindex, nofollow" />
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<?php
$sizeRecv0 = (isset ($_GET["sizeRecv0"]) ? demagicalize_string ($_GET["sizeRecv0"]) : "60%");
$sizeRecv1 = (isset ($_GET["sizeRecv1"]) ? demagicalize_string ($_GET["sizeRecv1"]) : "40%");
$sizeSend0 = (isset ($_GET["sizeSend0"]) ? demagicalize_string ($_GET["sizeSend0"]) : "60%");
$sizeSend1 = (isset ($_GET["sizeSend1"]) ? demagicalize_string ($_GET["sizeSend1"]) : "40%");
$sizeHelp0 = (isset ($_GET["sizeHelp0"]) ? demagicalize_string ($_GET["sizeHelp0"]) : "50%");
$sizeHelp1 = (isset ($_GET["sizeHelp1"]) ? demagicalize_string ($_GET["sizeHelp1"]) : "50%");
?>
<script type="text/javascript" src="chat.js"></script>
<title>QED-Chat v6</title>
</head>

<?php echo '<frameset rows="' . $sizeRecv0 . ', ' . $sizeRecv1 . '" onload="Init()">';?>
	<frame name="recv" src="receive.html">
	<?php echo '<frameset cols="' . $sizeSend0 . ', ' . $sizeSend1 . '">';?>
		<frame name="send" src="send.html">
		<?php echo '<frameset rows="' . $sizeHelp0 . ', ' . $sizeHelp1 . '">';?>
			<frame name="conf" src="config.html">
			<frame name="logs" src="logs.html">
		</frameset>
	</frameset>
	<noframes>
	  Diese Seite ben&ouml;tigt Frames um zu funktionieren.
	</noframes>
</frameset>

</html>