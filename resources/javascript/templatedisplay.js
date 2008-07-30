/***************************************************************
 *
 *  javascript functions regarding the templatedisplay extension
 *  relies on the javascript library "prototype"
 *
 *
 *  Copyright notice
 *
 *  (c) 2006-2008	Benjamin Mack <www.xnos.org>
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
 * $Id:  $
 ***************************************************************/

/**
 *
 * @author	Fabien Udriot
 */

var templatedisplay;

if (Prototype) {
	var Templatedisplay = Class.create({

		/**
		 * registers event listener and executes on DOM ready
		 */
		initialize: function() {
			
			//Event.observe(document, 'dom:loaded', function(){
			Event.observe(document, 'dom:loaded', function(){
				$$('#templatedisplay_templateBox a').each(function(object){
					Event.observe(object, 'click', templatedisplay.selectField);
					Event.observe($('templatedisplay_showJson'),'click',templatedisplay.toggleJsonBoxVisibility);
					Event.observe($('templatedisplay_editJson'),'click',templatedisplay.toggleJsonBoxDisable);
					Event.observe($('templatedisplay_saveConfigurationBt'),'click',templatedisplay.saveConfiguration);
				});
			});
			
		},
		
		saveConfiguration: function(){
			//var data = new Element('phparray')
			var data = '[{"table": "pages", "field": "title", "type": "text", "configuration": ""},{"table": "tt_content", "field": "uid", "type": "text", "configuration": ""}]'.evalJSON(true);
			
			var found = false;
			$(data).each(function(name, index){
				console.log(name);
				found = true;
			});
			
			console.log(found)
			
			$('templatedisplay_json').update(data.toJSON());
			
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
		
		toggleJsonBoxDisable: function(){
			if($('templatedisplay_json').disabled){
				$('templatedisplay_json').disabled = '';
            }
			else{
				$('templatedisplay_json').disabled = 'disabled';
            }
        },
		
		/**
         * Try to guess an association between a field and a marker
         */
		selectField: function(){
			var field = '';
			var table = '';
			
			// Cosmetic: add an editing icon above the marker
			$$('#templatedisplay_templateBox a').each(function(object){
				$(object).next().src = infomodule_path + 'exclamation.png';
			});
			$(this).next().src = infomodule_path + 'pencil.png';
			
			// Extract the field name
			field = this.innerHTML.replace(/#{3}FIELD\.([0-9a-zA-Z\.]+)#{3}/g,'$1');
			
			// Extract the table name's field
			var content = $$('#templatedisplay_templateBox')[0].innerHTML.split('templatedisplay/resources/images/pencil.png');
			content = content[0].split('###LOOP.');
			if(typeof(content[content.length - 1] == 'string')){
				content = content[content.length - 1].split(/#{3}/);
				table = content[0];
            }
			
			// Select the right entry in the select drop down
			if(table != '' && field != ''){
				$$('#templatedisplay_fields')[0].value = table + '.' + field;
            }
			else{
				$$('#templatedisplay_fields')[0].value = '';
            }
			
			// Show the other boxes that was hidden
			$('templatedisplay_fields').disabled = "";
			$('templatedisplay_typeBox').removeClassName('templatedisplay_hidden');
			$('templatedisplay_type').value = 'text';
			$('templatedisplay_configuationBox').removeClassName('templatedisplay_hidden');
        }

	});

	// Initialize the object
	templatedisplay = new Templatedisplay();
	
}
else{
	alert('Problem loading templatedisplay library. Check if Prototype is loaded')
}
