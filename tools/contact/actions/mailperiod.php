<!-- mailperiod start -->
<div class="mail-period">
<?php
$period = isset($_GET['period']) ? $_GET['period'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

echo _t('CONTACT_PERIOD').' : ';
?>
<div class="btn-group btn-group-sm" role="group" aria-label="<?php echo _t('CONTACT_PERIOD').' : '; ?>">
  <a href="<?php echo $this->href('', $this->getPageTag(), 'period=day'); ?>" class="btn <?php echo ($period == 'day') ? 'btn-primary' : 'btn-default'; ?>">
    <?php echo _t('CONTACT_DAILY'); ?>
  </a>
  <a href="<?php echo $this->href('', $this->getPageTag(), 'period=week'); ?>" class="btn <?php echo ($period == 'week') ? 'btn-primary' : 'btn-default'; ?>">
    <?php echo _t('CONTACT_WEEKLY'); ?>
  </a>
  <a href="<?php echo $this->href('', $this->getPageTag(), 'period=month'); ?>" class="btn <?php echo ($period == 'month') ? 'btn-primary' : 'btn-default'; ?>">
    <?php echo _t('CONTACT_MONTHLY'); ?>
  </a>
  <a href="<?php echo $this->href('', $this->getPageTag()); ?>" class="btn <?php echo (empty($period)) ? 'btn-primary' : 'btn-default'; ?>">
    <?php echo _t('CONTACT_FOREVER'); ?>
  </a>
</div>
<?php
// le nom du groupe est generé a partir de la page et de la periode
$groupname = 'Mail'.$this->getPageTag().ucfirst($period);

// une action d'abonnement ou desabonnement est demandée
if (!empty($action) and $this->GetUser()) {
    if ($action == 'subscribe') {
        if (!$this->UserIsInGroup($groupname, $this->GetUserName(), false)) {
            $this->SetGroupACL($groupname, $this->GetGroupACL($groupname)."\n".$this->GetUserName());
        }
    } elseif ($action == 'unsubscribe') {
        if ($this->UserIsInGroup($groupname, $this->GetUserName(), false)) {
            $newgroup = str_replace($this->GetUserName(), '', $this->GetGroupACL($groupname));
            $newgroup = explode("\n", $newgroup);
            $newgroup = array_map('trim', $newgroup);
            $newgroup = array_filter($newgroup);
            $newgroup = implode("\n", $newgroup);
            $this->SetGroupACL($groupname, $newgroup);
        }
    }
}

// on affiche la bonne periode
if (!empty($period)) {
    if ($this->getUser()) {
        if ($this->UserIsInGroup($groupname, $this->GetUserName(), false)) {
            echo '<a class="btn btn-danger btn-sm" href="'.$this->href('', $this->getPageTag(), 'period='.$period.'&action=unsubscribe').'"><i class="glyphicon glyphicon-envelope"></i> '._t('CONTACT_UNSUBSCRIBE_FOR_THIS_PERIOD').'</a>';
        } else {
            echo '<a class="btn btn-default btn-sm" href="'.$this->href('', $this->getPageTag(), 'period='.$period.'&action=subscribe').'"><i class="glyphicon glyphicon-envelope"></i> '._t('CONTACT_SUBSCRIBE_FOR_THIS_PERIOD').'</a>';
        }
    } else {
        echo _t('CONTACT_LOGIN_TO_GET_INFOS');
    }
}
?>
</div> <!-- /.mail-period -->
<!-- mailperiod end -->
