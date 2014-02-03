function replaceTextInPost(message, idPost, numSeparator, posToCheck) {
	var r=confirm(message); 
	
	if (r) {
		jQuery('#text_with_proposed_modifications').text('') ;
		jQuery('#wait_proposed_modifications').show() ;
		var arguments = {
			action: 'replaceWithProposedModifications_FR', 
			id:idPost, 
			num:numSeparator, 
			pos:posToCheck
		} 
		jQuery.post(ajaxurl, arguments, function(response) {
			jQuery('#text_with_proposed_modifications').html(response) ;
			jQuery('#wait_proposed_modifications').hide() ;
		});
	}
}

function validAllIssue(message, idPost) {
	replaceTextInPost(message, idPost, "ALL", "ALL") ;
}

function showEditor(idPost) {
	jQuery('#text_with_proposed_modifications').text('') ;
	jQuery('#wait_proposed_modifications').show() ;
	var arguments = {
		action: 'showEditorModif', 
		id:idPost
	} 
	jQuery.post(ajaxurl, arguments, function(response) {
		jQuery('#text_with_proposed_modifications').html(response) ;
		jQuery('#wait_proposed_modifications').hide() ;
	});
}

function cancelEditor(idPost) {
	jQuery('#text_with_proposed_modifications').text('') ;
	jQuery('#wait_proposed_modifications').show() ;
	var arguments = {
		action: 'cancelEditorModif', 
		id:idPost
	} 
	jQuery.post(ajaxurl, arguments, function(response) {
		jQuery('#text_with_proposed_modifications').html(response) ;
		jQuery('#wait_proposed_modifications').hide() ;
	});
}


function saveEditor(idPost) {
	textToUpload=jQuery("#editor_textarea").val();
	jQuery('#text_with_proposed_modifications').text('') ;
	jQuery('#wait_proposed_modifications').show() ;
	var arguments = {
		action: 'saveEditorModif', 
		text:textToUpload,
		id:idPost
	} 
	jQuery.post(ajaxurl, arguments, function(response) {
		jQuery('#text_with_proposed_modifications').html(response) ;
		jQuery('#wait_proposed_modifications').hide() ;
	});
}

function forceAnalysis() {
	jQuery('#forceAnalysis').attr('disabled', 'disabled');
	jQuery('#stopAnalysis').removeAttr('disabled');
	jQuery('#wait_analysis').show() ;
	var arguments = {
		action: 'forceAnalysisFormatting'
	} 
	jQuery.post(ajaxurl, arguments, function(response) {
		if ((""+response+ "").indexOf("PROGRESS - ") !=-1) {
			if (jQuery('#forceAnalysis').is(":disabled")) {
				jQuery('#table_formatting').html(response) ;
				forceAnalysis() ; 
			}
		} else {
			jQuery('#forceAnalysis').removeAttr('disabled');
			jQuery('#stopAnalysis').attr('disabled', 'disabled');
			jQuery('#table_formatting').html(response) ;
			jQuery('#wait_analysis').hide() ;
		}
	});
}

function stopAnalysis() {
	jQuery('#forceAnalysis').removeAttr('disabled');
	jQuery('#stopAnalysis').attr('disabled', 'disabled');
	jQuery('#wait_analysis').hide() ;
	
	var arguments = {
		action: 'stopAnalysisFormatting'
	} 
	jQuery.post(ajaxurl, arguments, function(response) {
		// nothing
	});
}


function viewFormattingIssue(idPost) {
	var arguments = {
		action: 'viewFormattingIssue', 
		id:idPost
	} 
	jQuery.post(ajaxurl, arguments, function(response) {
		jQuery("#viewFormattingIssue_edit").html(response);
	}).error(function(x,e) { 
		if (x.status==0){
			//Offline
		} else if (x.status==500){
			jQuery("#viewFormattingIssue_edit").html("Error 500: The ajax request is retried");
			viewFormattingIssue(idPost) ; 
		} else {
			jQuery("#viewFormattingIssue_edit").html("Error "+x.status+": No data retrieved");
		}
	});  
}

function resetFormattingIssue(idPost) {
	var r=confirm("Reset?"); 
	if (r) {
		var arguments = {
			action: 'resetFormattingIssue', 
			id:idPost
		} 
		jQuery.post(ajaxurl, arguments, function(response) {
			if (response=="OK") {
				window.location.href=window.location.href ; 
			} else {
				alert(response) ; 
			}
		}).error(function(x,e) { 
		if (x.status==0){
			//Offline
		} else if (x.status==500){
			jQuery("#viewFormattingIssue_edit").html("Error 500: The ajax request is retried");
			resetFormattingIssue(idPost) ; 
		} else {
			alert("Error "+x.status+": No data retrieved");
		}
	});  
	}
}

function acceptAllModificationProposed(idPost) {	
	var r=confirm("Accept all modifications?"); 
	
	if (r) {
		jQuery('#text_with_proposed_modifications').text('') ;
		jQuery('#wait_proposed_modifications').show() ;
		var arguments = {
			action: 'replaceWithProposedModifications_FR', 
			id:idPost, 
			num:"ALL", 
			pos:"ALL"
		} 
		jQuery.post(ajaxurl, arguments, function(response) {
			window.location.href=window.location.href ; 
		});
	}
}