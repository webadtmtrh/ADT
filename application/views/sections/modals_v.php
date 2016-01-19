<!-- Modal edit user profile-->
<div id="edit_user_profile" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <form action="<?php echo base_url().'user_management/profile_update' ?>" method="post">
  <div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
    <h3 id="myModalLabel">User details</h3>
  </div>
  <div class="modal-body">
   
		<table>
			<tr>

				<td><label >Full Name</label></td><td>
				<div class="input-prepend">
					<span class="add-on"><i class="icon-user"></i></span>
					<input style='height:2.1em' type="text" class="input-xlarge" name="u_fullname" id="u_fullname" required="" value="<?php echo $this->session->userdata('full_name') ?>" />
				</div></td>
			</tr>
			<tr>
				<td><label >Username</label></td><td>
				<div class="input-prepend">
					<span class="add-on"><i class="icon-user"></i></span>
					<input style='height:2.1em' type="text" class="input-xlarge" name="u_username" id="u_username" required="" value="<?php echo $this->session->userdata('username') ?>" />
				</div></td>
			</tr>
			<tr>
				<td><label>Email Address</label></td><td>
				<div class="input-prepend">
					<span class="add-on"><i class="icon-envelope"></i></span>
					<input style='height:2.1em' type="email" class="input-xlarge" name="u_email" id="u_email" value="<?php echo $this->session->userdata('Email_Address') ?>" />
				</div></td>
			</tr>
			<tr>
				<td><label>Phone Number</label></td><td>
				<div class="input-prepend">
					<span class="add-on"><i class="icon-plus"></i>254</span>
					<input style='height:2.1em' type="tel" class="input-large" name="u_phone" id="u_phone" value="<?php echo $this->session->userdata('Phone_Number') ?>"/>
				</div></td>
			</tr>
		</table>
	
  </div>
  
  <div class="modal-footer">
    <button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
    <input type="submit" class="btn btn-primary" value="Save changes">
  </div>
  </form>
</div>
<!-- Modal edit user profile end-->
<!-- Modal edit change password-->
<div id="user_change_pass" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
  <form action="<?php echo base_url().'user_management/profile_update' ?>" method="post">
  <div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
    <h3 id="myModalLabel">Change password</h3>
  </div>
  <div class="modal-body">
   <input type="hidden" name="base_url" id="base_url" value="<?php echo base_url() ?>" />
   <form id="fmChangePassword" action="<?php echo base_url().'user_management/save_new_password/1'?>" method="post" class="well">
		<span class="message error" id="error_msg_change_pass"></span>
		<div id="m_loadingDiv" style="display: none"><img style="width: 30px" src="<?php echo asset_url().'images/loading_spin.gif' ?>"></div>
		<br>
		<table>
			<tr>
			<td><label >Old Password</label></td><td><input type="password" name="old_password" id="old_password" required=""></td>
			</tr>
			<tr>
			<td><label >New Password</label></td><td><input type="password" name="new_password" id="new_password" required=""><span id="result"></span></td>
			</tr>
			<tr>
			<td><label >Confirm New Password</label></td><td>
			<input type="password" name="new_password_confirm" id="new_password_confirm" required="">
			</td>
			</tr>
		</table>

	</form>
	
  </div>
  
  <div class="modal-footer">
    <button class="btn" data-dismiss="modal" aria-hidden="true">Cancel</button>
    <input type="button" class="btn btn-primary btn_submit_pass" name="btn_submit_change_pass" id="btn_submit_change_pass" value="Save changes">
  </div>
  </form>
</div>
<!-- Modal edit change password end-->

<!-- Modal for synchronizing balances -->
<div id="drug_stock_balance_synch" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">  
    <div class="modal-header">
	    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
	    <h3 id="myModalLabel">Synchronization - Stock Balances</h3>
	</div>
	<div class="modal-body">  
		<span class="alert-info ">Please wait until the process is complete!</span>
        <div class="span5">
        	<!-- 
	    	<div id="div_tot_drugs" style="display: none">
	    		Number of drugs :<strong><span id="tot_drugs"></span></strong>
	    	</div> 
	    	-->
	    	<p>
		    <div class="progress progress_pharmacy_dsm progress-striped active">  
			  <div class="bar bar_dcb" style="width: 0%;">Drug Consumption</div> 
			</div>
			</p>
		    <p> 
		    <div class="progress progress_store progress-striped active">  
			  <div class="bar bar_dsb bar_store" style="width: 0%;">Main Store - Stock balance</div> 
			</div>  
			</p> 
			<p>
		    <div class="progress progress_pharmacy progress-striped active">  
			  <div class="bar bar_dsb bar_pharmacy" style="width: 0%;">Pharmacy - Stock balance</div> 
			</div>
			</p>
			 <p> 
		    <div class="progress progress_store_dsm progress-striped active">  
			  <div class="bar bar_dsm bar_store_dsm" style="width: 0%;">Main Store - Stock transactions</div> 
			</div>  
			</p> 
			<p>
		    <div class="progress progress_pharmacy_dsm progress-striped active">  
			  <div class="bar bar_dsm bar_pharmacy_dsm" style="width: 0%;">Pharmacy - Stock transactions</div> 
			</div>
			</p>
			
			<a class="sync_complete" href="#"></a>
	    </div>  
	    <div class="modal-footer">
		   <button class="btn" data-dismiss="modal" aria-hidden="true">Done</button>
		</div>
    </div>  
</div>
<!--  Modal for synchronizing balances end  -->



<!-- Confirmation message before synchronizing drug stock movement balance-->
<div id="confirmbox" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-header">
	    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
	    <h3 id="myModalLabel">Confirm before proceed</h3>
	</div>
	<div class="modal-body">
        <p id="confirmMessage" >
        	Please make sure you synchronize the <i><strong>stock balance</strong></i> before proceeding.
        </p>
    </div>
    <div class="modal-footer">
        <button class="btn" id="confirmFalse" data-dismiss="modal" aria-hidden="true">Cancel</button>
        <button class="btn btn-primary" id="confirmTrue" data-dismiss="modal" aria-hidden="true">Proceed</button>
    </div>
</div>
<!--
/*
 * Order Modal
 */
-->


<!-- Submit confirmation for maps -->
	<div id="confirmsubmission" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	    <div class="modal-header">
		    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
		    <h3 id="modalHeader">Confirm Delete</h3>
		</div>
		<div class="modal-body conf_maps_body">
	        
	    </div>
	    <div class="modal-footer">
	        <button class="btn order_btn" id="cFalse" data-dismiss="modal" aria-hidden="true">Cancel</button>
	        <button class="btn order_btn btn-primary" id="cTrue" data-dismiss="modal" aria-hidden="true">Proceed</button>
	    </div>
	</div>
<!-- Submit confirmation ends  maps-->

<!-- Login for escm -->
