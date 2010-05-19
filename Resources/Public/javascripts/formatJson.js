/* @autor Fabien Udriot <fabien.udriot@ecodev.ch> */

// Debug data
//data =
//[
//	{
//		"table" : "pages",
//		"field" : "title",
//		"type" : "text",
//		"configuration" : 'wr"ap" = <b>|</b>\ncase = upper'
//	},
//	{
//		"table" : "pages",
//		"field" : "title",
//		"type" : "text",
//		"configuration" : 'wrap = <b>|</b>\ncase = upper'
//	}
//]
//var jsonString = formatJson(data);
//console.log(jsonString)
function formatJson(object, level){
	if(typeof(level) == 'undefined'){
		level = 0;
    }
	
	// Defines somes variable
    var INTEND = formatedString = "";
    var NEWLINE = "\n";
	for(var loop = 0; loop < level; loop++){
		INTEND += "\t";
    }

	// Tries to get the length of the object
	var length = object.length;

	// True whenever object is an array ["value1","value2"]
	// False means object is an object {value1","value2}
	if(typeof(length) == 'number'){
		var _array = new Array();
		for (var index = 0; index < length; index ++) {
			if (typeof(object[index]) == 'object') {
				_array.push(formatJson(object[index],level + 1));
			}
		}
		// Coution: formatedString exists in the clausure, means the variable still exists in the recursive function
		formatedString = INTEND + '[' + NEWLINE;
		formatedString += _array.join(',' + NEWLINE) + NEWLINE;
		formatedString += INTEND + ']' + NEWLINE;
    }
	// Means this is an object
	else{
		formatedString += INTEND + '{' + NEWLINE;
		// Traverses the object which contains the real information
		var _array = new Array();
		for (var key in object) {
			_array.push(INTEND + "\t" + '"' + key + '" : "' + protectJsonString(object[key]) + '"');
		}
		formatedString += _array.join(',' + NEWLINE) + NEWLINE;
		formatedString += INTEND + '}';
    }

	return formatedString;
}

function protectJsonString(string){
	string = string.replace(/\n/g,"\\n");
	return string.replace(/"/g,"\\\"");
}
