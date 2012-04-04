<?php if (!defined('APPLICATION')) exit();

/**
 * Changelog
 * 0.1.1
 *   * fixed bug sending email to deleted users
 * 0.1.22
 *   * fix typo
 * 0.1.30
 *   * enforcing permissions - users can't subscribe to Categories that they don't have Vanilla.Discussions.View permission
 * 0.1.31
 *   * reestablish the "Subscribe everyone to everything" and improve it with "everything they can view" via some SQL juggling.
 * 0.1.32
 *   * fix bug where I left a die() in the code.
 * 0.1.33
 *   * finally understood how categories and permissions work, despite the lack of documentation in the code.
 * 0.2.1
 *   * do not send email on edit
 *   * translations
 */

$PluginInfo['EMailSubscribe'] = array(
   'Description' => 'Enables users to subscribe to receive e-mails when new posts in different Categories are posted',
   'Version' => '0.2.1',
   'RequiredApplications' => NULL,
   'RequiredTheme' => FALSE,
   'RequiredPlugins' => FALSE,
   'HasLocale' => FALSE,
   'Author' => 'Catalin David',
   'AuthorEmail' => 'c.david@jacobs-university.de'
);

/**
 * 
 * @author cdavid
 * Available locale translations:
 * 
 * T('DiscStart')
 * T('E-mail Subscriptions')
 * T("Your changes have been saved.")
 * T('Manage E-mail Subscriptions')
 * T('SubscribeAll')
 * T('General Settings')
 * T('Subscribe to all discussions')
 * T('Categories Settings')
 * T('Subscribe to category ')
 * T('Save')
 */

class EMailSubscribe extends Gdn_Plugin {

	public function PostController_AfterDiscussionSave_Handler(&$Sender){
		$Session = Gdn::Session();
		/* fixes send email on edit */
		if($Sender->RequestMethod == 'editdiscussion') return;		
		
		$UserName = $Session->User->Name;
		$ActivityHeadline = $UserName . T('DiscStart');
		$DiscussionID = $Sender->EventArguments['Discussion']->DiscussionID;
		$DiscussionName = $Sender->EventArguments['Discussion']->Name;
		$CategoryID = $Sender->EventArguments['Discussion']->CategoryID;
		$Story = $Sender->EventArguments['Discussion']->Body;

		$SQL = Gdn::SQL();
		$SqlUsers = $SQL->Select('distinct(m.UserID) UserID, u.Email, u.Name')
		       ->From('UserMeta m')
	          ->Join('User u', 'm.UserID = u.UserID')
		       ->Where('u.Deleted', '0')
		       ->Where('m.Name', 'Plugin.EMailSubscribe.Categ'.$CategoryID)
		       ->Where('m.Value>', 0) 
		       ->Get();
		$Users = array();
		while($User = $SqlUsers->NextRow(DATASET_TYPE_ARRAY)) {
			$Users[] = array( $User['UserID'], $User['Name'], $User['Email']);
		}

		foreach ($Users as $User) {
			$Email = new Gdn_Email();
			$Email->Subject(sprintf(T('[%1$s] %2$s'), Gdn::Config('Garden.Title'), $ActivityHeadline));
			$Email->To($User[2], $User[1]);
			//$Email->From(Gdn::Config('Garden.SupportEmail'), Gdn::Config('Garden.SupportName'));
			$Email->Message(
			sprintf(
			T($Story == '' ? 'EmailNotification' : 'EmailStoryNotification'),
			$ActivityHeadline,
			Url('/discussion/'.$DiscussionID.'/'.Gdn_Format::Url($DiscussionName), TRUE),
			$Story
			)
			);

			try {
				$Email->Send();
			} catch (Exception $ex) {
				echo $ex;
				die();
				// Don't do anything with the exception.
			}

			//Also bookmark the discussion for all those users
			$Sender->DiscussionModel->BookmarkDiscussion(
			$DiscussionID,
			$User[0],
			$Discussion = NULL
			);
		}
	}

	public function ProfileController_AfterAddSideMenu_Handler(&$Sender) {
		$SideMenu = $Sender->EventArguments['SideMenu'];
		$Session = Gdn::Session();
		$ViewingUserID = $Session->IsValid() ? $Session->UserID : 0;

		if ($Sender->User->UserID == $ViewingUserID) {
			$SideMenu->AddLink('Options', T('E-mail Subscriptions'), '/profile/subscriptions', FALSE, array('class' => 'Popup'));
		}
	}

	public function ProfileController_Subscriptions_Create(&$Sender) {
			
		$Session = Gdn::Session();
		$UserID = $Session->IsValid() ? $Session->UserID : 0;

		$UserMetaData = $this->GetUserMeta($UserID, '%');

		$ConfigArray = array();
		$Categs = array();
		//Get all categories
		if (C('Vanilla.Categories.Use')) {
			$CategoryModel = new CategoryModel();
			$CategoryData = $CategoryModel->GetFull('', 'Vanilla.Discussions.View');
			$aCategoryData = array();
			foreach ($CategoryData->Result() as $Category) {
				if ($Category->CategoryID <= 0)
				continue;
				$Categs[] = array($Category->CategoryID, $Category->Name);
			}
		}
		foreach ($Categs as $Category) {
			$ConfigArray['Plugin.EMailSubscribe.Categ'.$Category[0]] = NULL;
		}
		$Sender->Categs = $Categs;

		if($Sender->Form->AuthenticatedPostBack() === FALSE) {
			$ConfigArray = array_merge($ConfigArray, $UserMetaData);
			$Sender->Form->SetData($ConfigArray);
		} else {
			$Values = $Sender->Form->FormValues();
			$FrmValues = array_intersect_key($Values, $ConfigArray);

			foreach ($FrmValues as $MetaKey => $MetaValue) {
				$this->SetUserMeta($UserID, $this->TrimMetaKey($MetaKey), $MetaValue);
			}

			$Sender->StatusMessage = T("Your changes have been saved.");
		}
		$Sender->Render($this->GetView('settings.php'));
		echo '<script type="text/javascript">$(function(){$("#Form_Plugin-dot-EMailSubscribe-dot-All").click( function() {if ($("#Form_Plugin-dot-EMailSubscribe-dot-All:checked").val() !== undefined) $("input[id^=\"Form_Plugin-dot-EMailSubscribe-dot-\"]").val(["1"]); else $("input[id^=\"Form_Plugin-dot-EMailSubscribe-dot-\"]").val(["0"]);});});</script>';
	}

	public function Base_GetAppSettingsMenuItems_Handler(&$Sender) {
		$Menu = &$Sender->EventArguments['SideMenu'];
		$Menu->AddLink('Forum', T('E-mail Subscriptions'), 'settings/emailsubscription', 'Garden.Settings.Manage');
	}

	public function SettingsController_EMailSubscription_Create($Sender) {
		$Sender->Permission('Garden.Settings.Manage');
		$Sender->Title(T('Manage E-mail Subscriptions'));
		$Sender->AddSideMenu('settings/emailsubscription');
      $SQL = Gdn::SQL();
		if ($Sender->Form->AuthenticatedPostBack() === FALSE) {
			         		
		} else {
			
			$CategoryModel = new CategoryModel();
         $Cats = $CategoryModel->GetAll();
			
		   $Res = $SQL ->Select('cat.CategoryID','','CategoryID')
                     ->Select('u.UserID','','UserID')
                     ->Select('p.`Vanilla.Discussions.View`', '','Disc')
                     ->From('Permission p')                     
                     ->Join('UserRole ur', 'ur.RoleID = p.RoleID')
                     ->Join('Category cat', 'cat.CategoryID = p.JunctionID')
                     ->Join('User u', 'u.UserID = ur.UserID')
                     ->BeginWhereGroup()                                       
                        ->Where(array('p.JunctionTable'=> 'Category',
                                      'p.JunctionColumn' => 'PermissionCategoryID'))
                        ->OrWhere('p.JunctionTable is null and p.JunctionColumn is null')
                     ->EndWhereGroup()
                     ->Where('cat.CategoryID',-1) // Where CategoryID < 0, they have the default permissions for all the categories.
                     ->Get();       
         
         while($Data = $Res->NextRow(DATASET_TYPE_ARRAY)) {
            foreach ($Cats->Result() as $Category){
               if ($Category->CategoryID <= 0)
                  continue;                   
               $this->SetUserMeta($Data['UserID'],'Plugin.EMailSubscribe.Categ'.$Category->CategoryID, $Data['Disc']);
            }        
         }
		   
		   //now, assuming that happened, we can now get the real DISTINCT permissions and put them in place.		   
         $Res = $SQL ->Select('cat.CategoryID','','CategoryID')
                     ->Select('u.UserID','','UserID')
                     ->Select('p.`Vanilla.Discussions.View`', '','Disc')
                     ->From('Permission p')                     
                     ->Join('UserRole ur', 'ur.RoleID = p.RoleID')
                     ->Join('Category cat', 'cat.CategoryID = p.JunctionID')
                     ->Join('User u', 'u.UserID = ur.UserID')
                     ->BeginWhereGroup()                                       
                        ->Where(array('p.JunctionTable'=> 'Category',
                                      'p.JunctionColumn' => 'PermissionCategoryID'))
                        ->OrWhere('p.JunctionTable is null and p.JunctionColumn is null')
                     ->EndWhereGroup()
                     //->Where('p.`Vanilla.Discussions.View`', 1)                     
                     ->Where('cat.CategoryID>',0) // Where CategoryID < 0, they have the default permissions for all the categories.
                     ->Get();                   
         $Users = array();
		   
         while($Data = $Res->NextRow(DATASET_TYPE_ARRAY)) {
         	
         	$this->SetUserMeta($Data['UserID'],'Plugin.EMailSubscribe.Categ'.$Data['CategoryID'], $Data['Disc']);         	
         	        	
         }        
         
			$Sender->StatusMessage = T("Your changes have been saved.");
		}
		
		$Sender->Render($this->GetView('dashboard.php'));
	}

	//   //find a way to allow the admin to choose what categories the new users will be subscribed to.
	//   public function UserModel_AfterInsertUser_Handler($Sender) {
	//   	$UserID = $Sender->EventArguments['InsertUserID'];
	//   	$this->SetUserMeta($User, $this->TrimMetaKey("Plugin.EMailSubscribe.All"), "TRUE");
	//   }

	public function Setup() {
		$SQL = Gdn::SQL();
		$SQL->Delete('UserMeta', array('Name' => "Plugin.EMailSubscribe.All"));
	}
}
