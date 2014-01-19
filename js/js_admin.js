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