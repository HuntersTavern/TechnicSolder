var modInfos = [];
var _runningIndex = 0;
var modselect = $('#modselect').selectize();

function addModInfo(modInfo = []) {
    //add provided info to the modinfos arr.
    modInfos[_runningIndex] = modInfo;
    _runningIndex++;
}

function viewMod(identifier) {
    //Load mod info:
    modInfo = modInfos[identifier];
    modid = modInfo.modid;
    //load mods infos into form:
    $('#modInfoArrayIdentifier').val(identifier);
    $('#uploaded_filename').text(modInfo.filename)
    $('#pretty_name').val(modInfo.name);
    $('#name-new').val(modInfo.modid);
    $('#mod-version').val(modInfo.version);
    $('#loader').val(modInfo.loader);
    $('#mc-version').val(modInfo.mcversion);
    $('#author').val(modInfo.authorList.join());
    $('#description').val(modInfo.description);
    $('#link').val(modInfo.url);
    $('#confirmUploadButton').val(modInfo.modid);
    $('#cancelUploadButton').val(modInfo.modid);
    //Get Select options:
    var sel = modselect[0].selectize;
    var modSlugs = $.map(sel.options ,function(option) {
        return option.value;
    });
    console.log(modSlugs);
    
    if (modSlugs.find(element => element == modid)) {
        //Set select to modid:
        console.log('found index for ' + modid);
        sel.setValue(modid);
        disableInputs();
    } else {
        console.log('did not find index for ' + modid);
        sel.setValue(0);
        enableInputs();
    }
    
    //Show modal:
    $('#modInfoModal').modal('show');
}

function updateMod(identifier = null) {
    if (identifier == null) {
        identifier = $('#modInfoArrayIdentifier').val();
    }
    newModInfo = modInfos[identifier]; //load old values.
    delete modInfos[identifier]; //remove infos from array, will be set again at the end.
    modid = $('#name-new').val(); //update key
    newModInfo['type'] = $('#type').val();
    newModInfo['modid'] = modid;
    newModInfo['name'] = $('#pretty_name').val();
    newModInfo['side'] = $('#side').val();
    newModInfo['version'] = $('#mod-version').val();
    newModInfo['mcversion'] = $('#mc-version').val();
    newModInfo['loader'] = $('#loader').val();
    newModInfo['url'] = $('#link').val();
    newModInfo['description'] = $('#description').val();
    newModInfo['authorList'] = $('#author').val().split(',');
    modInfos[identifier] = newModInfo; //save new values.
    redrawTable();
}

$('#modselect').change(function() {
    //User changed the dropdown.
    console.log("dropdown change")
    if ($('#modselect').val() == 0) {
        enableInputs();
    } else {
        disableInputs();
    }
});

function enableInputs() {
    //unlock other inputs, user wants to add new mod.
    console.log("enabling inputs")
    $('#side').prop("disabled", false);
    $('#type').prop("disabled", false);
    $('#pretty_name').prop("disabled", false);
    $('#name-new').prop("disabled", false);
    $('#author').prop("disabled", false);
    $('#description').prop("disabled", false);
    $('#link').prop("disabled", false);
}

function disableInputs() {
    //lock other inputs, user wants to add new version.
    console.log("disabling inputs")
    $('#side').prop("disabled", true);
    $('#type').prop("disabled", true);
    $('#pretty_name').prop("disabled", true);
    $('#name-new').prop("disabled", true);
    $('#author').prop("disabled", true);
    $('#description').prop("disabled", true);
    $('#link').prop("disabled", true);
}

function redrawTable() {
    //clear table children
    $('#uploads').children('tr').remove()
    //Loop modInfos and readd them to table.
    for (i=0;i<Object.keys(modInfos).length;i++) {
        key = Object.keys(modInfos)[i];
        modInfo = modInfos[key];
        var formatFields = '<tr><td>'+modInfo.name+'</td><td>'+modInfo.modid+'</td><td>'+modInfo.version+'</td><td>'+modInfo.mcversion+'</td><td><div class="btn-group"><button class="btn btn-sm btn-info" onclick="viewMod(\''+key+'\')">View</button><!--<button class="btn btn-sm btn-success" onclick="confirmModUpload(\''+key+'\')">Confirm</button>--><button class="btn btn-sm btn-danger" onclick="cancelModUpload(\''+key+'\')">Cancel</button></div></td></tr>';
        $('#uploads').append(formatFields);
    }
}

function confirmModUpload(identifier = null, modSlug = null, modId = null, newMod = true) {
    //if no value is defined, it came from the form.
    if (identifier == null) {
        identifier = $('#modInfoArrayIdentifier').val();
    }
    var selected = $('#modselect').val();
    if (selected != 0) {
        newMod = false;
        modSlug = $('#modselect').val();
    } else {
        newMod = true;
        modSlug = $('#name-new').val();
    }
    uploadInfo = modInfos[identifier];
    //Confirm the upload, so that the version gets moved to the correct folder.
    $.ajax({
        type: "POST",
        url: "/mod/upload/confirm",
        data: "filename="+uploadInfo['filename']+"&modid="+modSlug+"&modversion="+uploadInfo['version'],
        success: function (data) {
            if (data.status == "success") {
                console.log(data);
                $.jGrowl('Confirmed Upload.', { group: 'alert-success' });
                //new mod or new version?
                if(newMod) {
                    createMod(identifier);
                } else {
                    modId = data.data[0].id;
                    addVersion(identifier,modSlug,modId);
                }
            } else if (data.status == "warning") {
                $.jGrowl('Warning: ' + data.reason, { group: 'alert-warning' });
                data.errors.array.forEach(error => {
                    $.jGrowl('Warning: ' + error.name[0], { group: 'alert-warning' });
                });
            } else {
                $.jGrowl('Error: ' + data.reason, { group: 'alert-danger' });
                data.errors.array.forEach(error => {
                    $.jGrowl('Warning: ' + error.name[0], { group: 'alert-warning' });
                });
            }
        },
        error: function (xhr, textStatus, errorThrown) {
            $.jGrowl(textStatus + ': ' + errorThrown, { group: 'alert-danger' });
        }
    });
    
    
}

function createMod(identifier) {
    //Map needed values
    createInfo = [];
    createInfo = modInfos[identifier];
    $.ajax({
        type: "POST",
        url: "/mod/create",
        data: "type="+createInfo['type']+"&name="+createInfo['modid']+"&pretty_name="+createInfo['name']+"&side="+createInfo['side']+"&author="+createInfo['authorList']+"&description="+createInfo['description']+"&link="+createInfo['url'],
        success: function (data) {
            console.log(data);
            if (data.status == "success") {
                $.jGrowl('Created mod.', { group: 'alert-success' });
                modId = data.data.id;
                addVersion(identifier,createInfo['modid'],modId);
            } else if (data.status == "warning") {
                $.jGrowl('Warning: ' + data.reason, { group: 'alert-warning' });
            } else {
                $.jGrowl('Error: ' + data.reason, { group: 'alert-danger' });
            }
        },
        error: function (xhr, textStatus, errorThrown) {
            $.jGrowl(textStatus + ': ' + errorThrown, { group: 'alert-danger' });
        }
    });
}

function addVersion(identifier, modSlug = 0, modId = 0) {
    if (modSlug == 0) {
        modSlug = $('#name-new').val();
    }
    //Map needed values
    versionInfo = modInfos[identifier];
    modVersion = versionInfo['version'];
    mcVersion = versionInfo['mcversion'];
    loader = versionInfo['loader'];
    $.ajax({
        type: "POST",
        url: "/mod/add-version",
        data: "mod-id="+modId+"&add-version="+modVersion+"&mcversion="+mcVersion+"&loader="+loader,
        success: function (data) {
            console.log(data);
            if (data.status == "success") {
                $.jGrowl('Added version '+modVersion+" to "+modSlug, { group: 'alert-success' });
                delete versionInfo;
                $('#modInfoModal').modal('hide');
                redrawTable();
            } else if (data.status == "warning") {
                $.jGrowl('Warning: ' + data.reason, { group: 'alert-warning' });
            } else {
                $.jGrowl('Error: ' + data.reason, { group: 'alert-danger' });
            }
        },
        error: function (xhr, textStatus, errorThrown) {
            $.jGrowl(textStatus + ': ' + errorThrown, { group: 'alert-danger' });
        }
    });
}

function cancelModUpload(identifier = null) {
    //if not value is defined, it came from the form.
    if (identifier == null) {
        identifier = $('#modInfoArrayIdentifier').val();
    }
    //remove from list, redraw table.
    delete modInfos[identifier];
    $('#modInfoModal').modal('hide');
    redrawTable();
}

/*$("#name").slugify('#pretty_name');
$(".modslug").slugify("#pretty_name");
$("#name").keyup(function() {
	$(".modslug").html($(this).val());
});*/

$('.changeSaveTrigger').keyup(function() {
    updateMod(null);
});

$(document).ready(function() {

	/*$('.custom-file-upload').on('click', function(e){
		$('#modupload').click();
	});*/

	$('input[type=file]').change(function(){

		$(this).simpleUpload("/mod/upload", {

			allowedExts: ["jar","zip"],
			allowedTypes: [
				"application/java-archive",
				"application/x-java-archive",
				"application/x-jar",
				"application/zip",
				"application/octet-stream",
				"application/x-zip-compressed"
			],
			maxFileSize: 500000000, //500MB in bytes

			start: function(file){
				//upload started

				this.row = $('<tr class="modUpload"></tr>');
				this.progressBar = $('<div class="progressBar"></div>');
				this.cancelButton = $('<div class="cancelButton">x</div>');

				/*
				* Since "this" differs depending on the function in which it is called,
				* we need to assign "this" to a local variable to be able to access
				* this.upload.cancel() inside another function call.
				*/

				var that = this;

				this.cancelButton.click(function(){
					that.upload.cancel();
					//now, the cancel callback will be called
				});

				this.row.append(this.progressBar).append(this.cancelButton);
				$('#uploads').append(this.row);

			},

			progress: function(progress){
				//received progress
				this.progressBar.width(progress + "%");
			},

			success: function(data){
				//upload successful
				this.progressBar.remove();
				this.cancelButton.remove();

				if (data.success) {
					//now fill the block with the format of the uploaded file
					var format = data.format;
                    var modInfo = data.modInfo;
					var filename = data.uploadedFile;
					if(modInfo == false || modInfo.length == 0) {
						//Set default values
						modInfo = [];
						modInfo["name"] = filename;
						modInfo["modid"] = filename;
						modInfo["authorList"] = ['none', 'defined'];
						modInfo["description"] = '';
						modInfo["url"] = '';
						modInfo["version"] = 0;
						modInfo["mcversion"] = 0;
					}
					modInfo["filename"] = filename;
					addModInfo(modInfo);
					redrawTable();
				} else {
					//our application returned an error
					var error = data.error.message;
					var errorDiv = $('<div class="error"></div>').text(error);
					this.row.append(errorDiv);
				}
			},

			error: function(error){
				//upload failed
				this.progressBar.remove();
				this.cancelButton.remove();
				var error = error.message;
				var errorDiv = $('<div class="error"></div>').text(error);
				this.row.append(errorDiv);
			},

			cancel: function(){
				//upload cancelled
				this.block.fadeOut(400, function(){
					$(this).remove();
				});
			}
		});
	});
});