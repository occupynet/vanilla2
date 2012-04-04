<?php if(!defined('APPLICATION')) exit(); ?>
<h2><?php echo T('E-mail subscription Settings') ?></h2>

<?php 
echo $this->Form->Open();
echo $this->Form->Errors();
?>
<ul>
   <li>
      <?php
         echo $this->Form->Label(T('General Settings'));
         echo $this->Form->CheckBox('Plugin.EMailSubscribe.All', T('Subscribe to all discussions'));
      ?>
   </li>
   <?php
      if (C('Vanilla.Categories.Use')) {
         echo '<li>';
         echo $this->Form->Label(T('Categories Settings'));
         foreach ($this->Categs as $Category) {
            echo $this->Form->CheckBox('Plugin.EMailSubscribe.Categ'.$Category[0], T('Subscribe to category ').$Category[1]);
         }
         echo '</li>';
      }
   ?>      
</ul>
<?php 
echo $this->Form->Close(T('Save'));