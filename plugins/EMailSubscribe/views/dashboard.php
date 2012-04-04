<?php if(!defined('APPLICATION')) exit(); ?>
<h2><?php echo T('E-mail subscription Settings') ?></h2>

<?php 
//echo 'Unfortunately, I found no easy way to do this, so this functionality is disabled (at least for now).';

echo $this->Form->Open();
echo $this->Form->Errors();

?>
<ul>
   <li>
      <?php
         echo $this->Form->Button(T('SubscribeAll'), 
               array('class' => 'Button CommentButton'));         
      ?>
   </li>   
</ul>

<?php
 
echo $this->Form->Close();
