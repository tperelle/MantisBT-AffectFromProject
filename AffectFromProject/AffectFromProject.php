<?php
class AffectFromProjectPlugin extends MantisPlugin {
    
	// Register plugin in core
	function register() {
        $this->name = 'AffectFromProject';    	# Proper name of plugin
		$this->description = 'Permet d\'indiquer un utilisateur par projet pour réaliser une assignation automatique des tickets';    # Short description of the plugin			
        $this->page = '';           			# Default plugin page

        $this->version = '1.0';     			# Plugin version string
        $this->requires = array(    			# Plugin dependencies, array of basename => version pairs
            'MantisCore' => '1.3.0',  			# Should always depend on an appropriate version of MantisBT
            );

        $this->author = 'Thomas Perelle';       # Author/team name
        $this->contact = 'thomas@perelle.com';  # Author/team e-mail address
        $this->url = '';            			# Support webpage
    }
	
	// Update database schema for plugin needs	
	function schema() {
		return array (
			array( 'CreateTableSQL', array( plugin_table( 'assignment' ), "
											id I UNSIGNED NOTNULL PRIMARY AUTOINCREMENT,
											user_id I UNSIGNED NOTNULL DEFAULT '0',
											project_id I UNSIGNED NOTNULL DEFAULT '0' ",
										array(
											'mysql' => 'DEFAULT CHARSET=utf8',
											'pgsql' => 'WITHOUT OIDS',
										)
									) 
			)
		);
	}
	
	// Hook some events
	function hooks() {
        return array(
			'EVENT_MANAGE_PROJECT_CREATE_FORM' => 'add_field',
			'EVENT_MANAGE_PROJECT_UPDATE_FORM' => 'add_field',
			'EVENT_MANAGE_PROJECT_CREATE' => 'update_assignment',
			'EVENT_MANAGE_PROJECT_UPDATE' => 'update_assignment',
			'EVENT_REPORT_BUG' => 'affect',
        );
    }
	
	// Callback to add an input field in update project page to set up assignment 
	function add_field($p_event, $p_chained_param) {
		
		$undefine_selected = "selected";
		
		// On project update, select actual assignment 
		if( isset($_GET["project_id"]) ) {
			$project_id = $_GET["project_id"];
			$user_id = $this->get_assignment((int)$project_id);
			if ( $user_id > 0 ) $undefine_selected = "";
		}
		
		// Get user list
		$t_query = 'SELECT * FROM {user} WHERE enabled=true ORDER BY username ASC';
		$t_result = db_query( $t_query );
		$t_users = array();

		while( $t_row_u = db_fetch_array( $t_result ) ) {
			$t_users[] = $t_row_u;
		}
		$t_user_count = count( $t_users );
		
		echo '<div class="field-container">';
			echo '<label for="project-affectation"><span>Assignation</span></label>';
			echo '<span class="select">';
				echo '<select id="project-affectation" name="affectation">';
					echo '<option value="undefined" '.$undefine_selected.' > </option>';
					for( $i=0; $i<$t_user_count; $i++ ) {
						# prefix user data with u_
						$t_user = $t_users[$i];
						extract( $t_user, EXTR_PREFIX_ALL, 'u' );
						if( $u_id == $user_id) $selected = " selected";
						else $selected = "";
						echo '<option value="'.string_display_line( $u_id ).'"'.$selected.'>'.string_display_line( $u_username ).'</option>';
					}
				echo '</select>';
			echo '</span>';
			echo '<span class="label-style"></span>';
		echo '</div>';
	}
	
	// Update assignment in database
	function update_assignment($p_event, $p_chained_param) {
		if( isset($_POST["project_id"]) && isset($_POST["affectation"]) ){
			$project_id = $_POST["project_id"];
			$assignment = $_POST["affectation"];
			
			// If a user is selected
			if( $assignment != "undefined") {
				
				// If project assignment is different to actual value in database
				if ( $this->get_assignment( $project_id ) != $assignment ) {
					// Delete previous assignment 
					$this->delete_previous_assignment( $project_id );
					
					// Insert new assignment 
					$this->add_assignment($project_id, $assignment);
				}
			}
			
			// No user selected
			else
				// Delete previous assignment if exists
				$this->delete_previous_assignment( $project_id );
		}
	}
	
	// Get user id from an assignment 
	function get_assignment( $project_id ) {
		$t_query = 'SELECT user_id from '.plugin_table( 'assignment' ).' WHERE project_id = '.$project_id.';';
		$t_result = db_query( $t_query );
		if (count($t_result) > 0) {
			$row = db_fetch_array( $t_result );
			return $row["user_id"];
		}
		else return 0;
	}
	
	// Delete assignment for a specific project id
	function delete_previous_assignment( $project_id ) {
		$t_query = 'DELETE from '.plugin_table( 'assignment' ).' WHERE project_id = '.$project_id.';';
		$t_result = db_query( $t_query );
		return true;
	}
	
	// Add assignment in database
	function add_assignment( $project_id, $user_id ) {
		$t_query = 'INSERT INTO '.plugin_table( 'assignment' ).' VALUES(DEFAULT,'.$user_id.', '.$project_id.');';
		$t_result = db_query( $t_query );
		return true;
	}
	
	// Callback to affect a new ticket with the affectation value of the concern project
	function affect( $p_event, $p_bug_data ) {
		$p_bug_data->__set('handler_id',$this->get_assignment( $p_bug_data->project_id ));
		$p_bug_data->validate();
		$p_bug_data->update();
	}
}