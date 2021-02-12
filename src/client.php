<?php
namespace Stanford\GoogleStorage;
/** @var \Stanford\GoogleStorage\GoogleStorage $this */

?>
<script src="<?php echo $this->getUrl('assets/js/client.js') ?>"></script>
<script>
    Client.fields = <?php echo json_encode($this->getFields()); ?>;
    Client.getSignedURLAjax = "<?php echo $this->getUrl('ajax/get_signed_url.php', false, true) . '&pid=' . $this->getProjectId() ?>"
    Client.recordId = "<?php echo $this->getRecordId() ?>"
    Client.eventId = "<?php echo $this->getEventId() ?>"
    Client.instanceId = "<?php echo $this->getInstanceId() ?>"
    window.onload = setTimeout(function () {
        Client.init();
    }, 500)
</script>