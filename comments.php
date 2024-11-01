<?php
/**
 * The template for displaying Comments.
 */
$opt = wpue_get_options();
?>

<script>
var transport = new easyXDM.Socket({
	remote: "<?php echo $opt['wpue_full_secure'] == '1' ? WPUE_PROTOCOL_SECURITY : WPUE_PROTOCOL ?>://<?php echo WPUE_DOMAIN; ?>/usite/webwidgetiframe/?public_key=<?php echo $opt['wpue_publickey']; ?>&link=<?php echo urlencode(wpue_current_url()); ?>&onlyvoice=<?php echo $opt['wpue_onlyvoice']; ?>",
	swf: "<?php echo $opt['wpue_xdmlink'] ?>/easyxdm.swf",
	container: "wpueContainer",
	onMessage: function(message, origin){
	    iframe = $('#wpueContainer iframe').css('height', message).css('width', '100%');
	}
});
</script>

<div id='wpueContainer'<?php echo $opt['wpue_custom_width'] ? " style='width:".htmlspecialchars($opt['wpue_custom_width']."'") : ''; ?>></div>
<?php /*<iframe  id='wpueIframe' src="<?php echo $opt['wpue_full_secure'] == '1' ? WPUE_PROTOCOL_SECURITY : WPUE_PROTOCOL ?>://<?php echo WPUE_DOMAIN; ?>/usite/webwidgetiframe/?public_key=<?php echo $opt['wpue_publickey']; ?>&link=<?php echo urlencode(wpue_current_url()); ?>" name="uerecorder" width="<?php echo $opt['wpue_custom_width'] ? htmlspecialchars($opt['wpue_custom_width']) : '100%'; ?>" frameborder="0" allowtransparency="true" scrolling="no"></iframe><br /> */ ?>