/***************************************************************
 *
 *  javascript functions regarding the templatedisplay extension
 *  relies on the javascript library "prototype"
 *
 *
 *  Copyright notice
 *
 *  (c) 2006-2008	Fabien Udriot <typo3@omic.ch>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 t3lib/ library provided by
 *  Kasper Skaarhoj <kasper@typo3.com> together with TYPO3
 *
 *  Released under GNU/GPL (see license file in tslib/)
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 *  This copyright notice MUST APPEAR in all copies of this script
 *
 * $Id$
 ***************************************************************/

/**
 *
 * @author	Fabien Udriot
 */

var templatedisplay;

if (Prototype) {
	var Templatedisplay = Class.create({

		/**
		 * Stores the datasource for performance
		 */
		records: '',

		/**
		 * Registers event listener and executes on DOM ready
		 */
		initialize: function() {

			Event.observe(document, 'dom:loaded', function(){
				// Things may happen wrong
				try {
					// Clickable links wrapping marker ###FIELD.xxx###
					$$('#templatedisplay_templateBox a').each(function(element){
						templatedisplay.initializeImages(element);
						Event.observe(element, 'click', templatedisplay.selectField);
					});

					// The 2 tab buttons
					Event.observe($('templatedisplay_tab1'), 'click', templatedisplay.showTab1);
					Event.observe($('templatedisplay_tab2'), 'click', templatedisplay.showTab2);

					// Checkbox "show json" -> displays the textarea that contains the json
					Event.observe($('templatedisplay_showJson'), 'click', templatedisplay.toggleJsonBoxVisibility);

					// Checkbox "edit json"
					Event.observe($('templatedisplay_editJson'), 'click', templatedisplay.toggleJsonBoxReadonly);

					// The save configuration button
					Event.observe($('templatedisplay_saveConfigurationBt'), 'click', templatedisplay.saveConfiguration);

					// Drop down menu that contains the different type (text - image - link - email - user)
					Event.observe($('templatedisplay_type'), 'change', templatedisplay.showSnippetBox);

					// Textarea that content the HTML template.
					Event.observe($('templatedisplay_htmlContent'), 'keyup', function(){
						tx_templatedisplay_hasChanged = true;
					});

					// Attaches event onto the snippet icon
					$$('.templatedisplay_snippetBox a').each(function(record, index){
						Event.observe($(record),'click',function(){
							var parent = $(this).parentNode;
							var type = parent.id.replace('templatedisplay_snippet','');
							var position = '';
							var thisRef = this;
							$$('#' + parent.id + ' a').each(function(linkRef, index){
								if (thisRef == linkRef) {
									position = index + 1;
								}
							});
							if ($('snippet' + type + position) != null) {
								var code = $('snippet' + type + position).innerHTML
								code = code.replace('\n<![CDATA[\n','');
								code = code.replace(']]>\n','');
								$('templatedisplay_configuration').value = code;
							}
							else {
								alert('No snippet found!')
							}

						});
					});
				}
				catch(e) {
					return;
				}
			});

		},

		/**
		 * Whenever the user has clicked on tab "mapping"
		 */
		showTab1: function() {
			// Makes sure there is content to send
			if ($('templatedisplay_htmlContent').value != '') {

				// If content has changed, sends an ajax request
				if (tx_templatedisplay_hasChanged) {

					// No uid to send (case: new record)
					if (tx_templatedisplay_uid.search('NEW') == -1) {

						// GUI changes
						$('templatedisplay_htmlContent').setStyle("opacity: 0.5");
						$$('#templatedisplay_html div')[0].removeClassName('templatedisplay_hidden');

						// Sends the content in an Ajax request
						new Ajax.Request("ajax.php", {
							method: "post",
							parameters: {
								"ajaxID": "templatedisplay::saveTemplate",
								"uid" : tx_templatedisplay_uid,
								"template" : $('templatedisplay_htmlContent').value
							},
							onComplete: function(xhr) {
								if (xhr.responseText != 0) {
									$('templatedisplay_tab2').parentNode.removeClassName('tabact');
									$('templatedisplay_tab1').parentNode.removeClassName('tab');
									$('templatedisplay_tab1').parentNode.addClassName('tabact');
									$('templatedisplay_mapping').removeClassName('templatedisplay_hidden');
									$('templatedisplay_html').addClassName('templatedisplay_hidden');
									$('templatedisplay_htmlContent').setStyle("opacity: 1");
									$$('#templatedisplay_html div')[0].addClassName('templatedisplay_hidden');

									// Reinject the new HTML
									$('templatedisplay_templateBox').innerHTML = xhr.responseText;

									// clickable link on marker ###FIELD.xxx###
									$$('#templatedisplay_templateBox a').each(function(element){
										templatedisplay.initializeImages(element);
										Event.observe(element, 'click', templatedisplay.selectField);
									});
									tx_templatedisplay_hasChanged = false;
								}

							}.bind(this),
							onT3Error: function(xhr) {
							//	console.log(xhr);
							}.bind(this)
						});
					}
					else {
						alert('Plase, save the record first the by the means of the save button.');
					}
				}
				else {
					// Switch to the other tab
					$('templatedisplay_tab2').parentNode.removeClassName('tabact');
					$('templatedisplay_tab1').parentNode.removeClassName('tab');
					$('templatedisplay_tab1').parentNode.addClassName('tabact');
					$('templatedisplay_mapping').removeClassName('templatedisplay_hidden');
					$('templatedisplay_html').addClassName('templatedisplay_hidden');
				}
			}
			else {
				alert('No HTML content defined! Please add some one.');
			}
		},

		/**
		 * Whenever the user has clicked on tab "HTML"
		 */
		showTab2: function() {
			$('templatedisplay_tab1').parentNode.removeClassName('tabact');
			this.parentNode.removeClassName('tab');
			this.parentNode.addClassName('tabact');
			$('templatedisplay_mapping').addClassName('templatedisplay_hidden');
			$('templatedisplay_html').removeClassName('templatedisplay_hidden');
		},

		/**
		 * Shows the right snippet box, according to the value
		 */
		showSnippetBox: function(type){
			if (typeof(type) == 'object') {
				type = this.value;
			}

			$$('.templatedisplay_snippetBox').each(function(record, index){
				record.addClassName('templatedisplay_hidden');
			});
			if ($('templatedisplay_snippet' + type)) {
				$('templatedisplay_snippet' + type).removeClassName('templatedisplay_hidden');
			}
		},

		/**
		 * Fetches the form informations and save them into the datasource.
		 */
		saveConfiguration: function(){

			// Cosmetic changes
			$('loadingBox').removeClassName('templatedisplay_hidden');

			var records = new Array();

			// Try parsing the existing datasource
			try{
				if($('templatedisplay_json').value != ''){
					records = $('templatedisplay_json').value.evalJSON(true);
				}
			}
			catch(error){
				alert('JSON transformation has failed!\n\n' + error)
				return;
			}

			// Get the formular value
			var offset = '';
			var content = $('templatedisplay_fields').value.split('.');
			var type = $('templatedisplay_type').value;
			var configuration = $('templatedisplay_configuration').value;
			var marker = $('templatedisplay_marker').value;
			var newRecord = '{"marker": "' + marker + '", "table": "' + content[0] + '", "field": "' + content[1] + '", "type": "' + type + '", "configuration": "' + protectJsonString(configuration) + '"}'
			newRecord = newRecord.evalJSON(true);

			// Make sure the newRecord does not exist in the datasource. If yes, remember the offset of the record for further use.
			$(records).each(function(record, index){
				if(record.marker == newRecord.marker){
					offset = index;
				}
			});

			// True, when new record => new position in the datasource
			if (typeof(offset) == 'string') {
				offset = records.length;
			}
			records[offset] = newRecord;
			//console.log(newRecord);

			// Reinject the JSON in the textarea
			//formatJson is a method from formatJson
			$('templatedisplay_json').value = formatJson(records);

			// Sends the content in an Ajax request
			new Ajax.Request("ajax.php", {
				method: "post",
				parameters: {
					"ajaxID": "templatedisplay::saveConfiguration",
					"uid" : tx_templatedisplay_uid,
					"mappings" : $('templatedisplay_json').value
				},
				onComplete: function(xhr) {
					if(xhr.responseText == 1){
						// Change the accept icon and the type icon
						var image1 = $$('img[src="' + infomodule_path + 'pencil.png"]')[0];
						var image2 = image1.nextSibling;
						//image1.src = infomodule_path + 'accept.png';
						//image1.title = 'Status: OK';
						image2.src = LOCALAPP.icons[type];
						image2.title = LOCALAPP.labels[type];

						//$('templatedisplay_typeBox').addClassName('templatedisplay_hidden');
						//$('templatedisplay_configuationBox').addClassName('templatedisplay_hidden');
						//$('templatedisplay_configuration').value = '';
						//$('templatedisplay_fields').value = '';
						//$('templatedisplay_fields').disabled = "disabled";
						$('loadingBox').addClassName('templatedisplay_hidden');
					}

				}.bind(this),
				onT3Error: function(xhr) {
					//console.log(xhr);
				}.bind(this)
			});
		},

		toggleJsonBoxVisibility: function(){
			//templatedisplay_hidden
			if($('templatedisplay_json').className == 'templatedisplay_hidden'){
				$('templatedisplay_json').className = '';
				$('templatedisplay_editJson').className = '';
				$('templatedisplay_labelEditJson').className = '';
			}
			else{
				$('templatedisplay_json').className = 'templatedisplay_hidden';
				$('templatedisplay_editJson').className = 'templatedisplay_hidden';
				$('templatedisplay_labelEditJson').className = 'templatedisplay_hidden';
			}
		},

		toggleJsonBoxReadonly: function(){
			if($('templatedisplay_json').getAttribute('readonly') == 'readonly'){
				$('templatedisplay_json').removeAttribute('readonly');
			}
			else{
				$('templatedisplay_json').setAttribute('readonly','readonly');
			}
		},

		/**
		 * Defines the images above the clickable markers. Can be exclamation.png or accept.png.
		 * And defines the image type at the right site (text.png - image.png - linkToDetail.png - linkToFile.png - linkToPage.png - email.png)
		 */
		initializeImages: function(element){
			// Extract the field name
			// 2 possible cases: either it is an OBJECT => no mapping with field, or it is FIELD => mapping
			if (element.innerHTML.search('OBJECT.') > -1) {
				var pattern = /#{3}OBJECT\.([0-9a-zA-Z\_\-\.]+)#{3}/g;
			}
			else {
				var pattern = /#{3}FIELD\.([0-9a-zA-Z\_\-\.]+)#{3}/g;
			}
			var field = element.innerHTML.replace(pattern,'$1');
			
			// Extract the table name's field
			var table = '';

			// Get a reference of the first image. (accept.png || exclamation.png)
			var image = $(element.nextSibling)

			// Add a little mark in order to be able to split the content in the right place
			image.src = '';
			var content = $$('#templatedisplay_templateBox')[0].innerHTML.split('src=""');
			content = content[0].split(/LOOP *\(/);
			if(typeof(content[content.length - 1] == 'string')){
				content = content[content.length - 1].split(/#{3}/);
				table = content[0];
			}

			// True, when no JSON information is available -> put an empty icon
			if($('templatedisplay_json').value == ''){
				image.src = infomodule_path + 'exclamation.png';
				image.title = 'Status: not matched'
				return;
			}

			// Fetch the records and store them for performance
			if(templatedisplay.records == ''){
				try{
					templatedisplay.records = $('templatedisplay_json').value.evalJSON(true);
				}
				catch(error){
					alert('JSON transformation has failed!\n You should check the datasource \n' + error)
					return;
				}
			}

			// Make sure the newRecord does not exist in the datasource. If yes, remember the offset of the record for further use.
			var type = '';
			$(templatedisplay.records).each(function(record, index){
				if(record.marker == 'FIELD.' + field || record.marker == 'OBJECT.' + field){
					type = record.type;
				}
			});

			// Puts the right icon wheter a marker is defined or not
			if (type != '') {
				image.src = infomodule_path + 'accept.png';
				image.title = 'Status: OK';

				// Puts an other icon according to the type of the link
				$(image.nextSibling).src = LOCALAPP.icons[type];
				$(image.nextSibling).title = LOCALAPP.labels[type];
			}
			else{
				image.src = infomodule_path + 'exclamation.png';
				image.title = 'Status: not matched';
			}
		},

		/**
		 * Try to guess an association between a field and a marker. When a field is found, do a few things
		 *
		 * 1) Sets the correct value for dropdown menu templatedisplay_type
		 * 2) Changes the icon above the marker
		 * 3) Shows the right snippetbox
		 */
		selectField: function(){

			// 2 possible cases: either it is an OBJECT => no mapping with field, or it is FIELD => mapping
			if (this.innerHTML.search('OBJECT.') > -1) {
				$('templatedisplay_fieldBox').addClassName('templatedisplay_hidden');
				var markerType = 'OBJECT';
				var pattern = /#{3}OBJECT\.([0-9a-zA-Z\_\-\.]+)#{3}/g;
			}
			else {
				$('templatedisplay_fieldBox').removeClassName('templatedisplay_hidden');
				var markerType = 'FIELD';
				var pattern = /#{3}FIELD\.([0-9a-zA-Z\_\-\.]+)#{3}/g;
			}

			// Resets the local datasource
			templatedisplay.records = '';

			// Cosmetic: add an editing icon above the marker
			$$('#templatedisplay_templateBox a').each(function(element){
				templatedisplay.initializeImages(element);
			});
			$(this).next().src = infomodule_path + 'pencil.png';
			$(this).next().title = 'Status: editing';

			// Extract the field name
			var field = this.innerHTML.replace(pattern,'$1');

			// Extract the table name's field
			var content = $$('#templatedisplay_templateBox')[0].innerHTML.split('templatedisplay/resources/images/pencil.png');
			content = content[0].split(/LOOP *\(/);

			var table = '';
			if(typeof(content[content.length - 1] == 'string')){
				content = content[content.length - 1].split(/\) *--&gt;/);
				table = content[0];
			}

			// means the table was not successfully guessed
			if (table == '' || table.search(' ') != -1) {
				table = tx_templatedisplay_defaultTable;
			}

			var marker = markerType + '.' + field;
			$$('#templatedisplay_marker')[0].value = marker;

			// Show the other boxes that were previously hidden (configuration box - dropdown menu type etc...)
			$('templatedisplay_fields').disabled = "";
			$('templatedisplay_typeBox').removeClassName('templatedisplay_hidden');
			$('templatedisplay_configuationBox').removeClassName('templatedisplay_hidden');

			// Makes sure the JSON != null, otherwise it will generate an error
			if ($('templatedisplay_json').value.length == 0) {
				$('templatedisplay_json').value = '[]';
			}

			var currentRecord = '';
			records = $('templatedisplay_json').value.evalJSON(true);
			// Tries to find out which field has been clicked
			$(records).each(function(record, index){
				if(record.marker == marker){
					currentRecord = record;
				}
			});

			// Select the right entry in the select drop down
			if(typeof(currentRecord) == 'object'){
				$$('#templatedisplay_fields')[0].value = currentRecord.table + '.' + currentRecord.field;
			}
			else if(table != '' && field != ''){
				$$('#templatedisplay_fields')[0].value = table + '.' + field;
			}
			else{
				$$('#templatedisplay_fields')[0].value = '';
			}

			// currentRecord is a reference to the ###FIELD.xxx###
			if(typeof(currentRecord) == 'object'){
				$('templatedisplay_type').value = currentRecord.type;
				$('templatedisplay_configuration').value = currentRecord.configuration;

				// (Cosmetic) Displays the right snippet Box
				templatedisplay.showSnippetBox(currentRecord.type);
			}
			// means the field has not been found for some reasons
			else{
				$('templatedisplay_type').value = 'text';
				$('templatedisplay_configuration').value = '';
			}

			// Makes the control panel facing the marker. (position control panel at the same line)
			// Calculates some values
			var heightDocHeader = $('typo3-docheader').getHeight();
			var controlPanelOffset = $('templatedisplay_cellLeft').cumulativeOffset().top;
			var heightScroll = $('templatedisplay_templateBox').cumulativeScrollOffset().top - 0 + heightDocHeader;

			// Moves when necessary
			if (heightScroll > controlPanelOffset) {
				var margin = heightScroll - controlPanelOffset;
				$('templatedisplay_fieldBox').setStyle({
					'marginTop': margin + 'px'
					});
			}
			else {
				$('templatedisplay_fieldBox').setStyle({
					'marginTop': '0px'
				});
			}
		}

	});

	// Initialize the object
	templatedisplay = new Templatedisplay();

}
else{
	alert('Problem loading templatedisplay library. Check if Prototype is loaded')
}


