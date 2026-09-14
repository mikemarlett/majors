/**
 * @license Copyright (c) 2003-2017, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
	config.toolbarGroups = [
		{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
		{ name: 'links', groups: [ 'links' ] },
		{ name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'bidi', 'paragraph' ] },
	];

	config.removeButtons = 'Underline,Strike,Anchor,Language,BidiRtl,BidiLtr,CreateDiv';//Iframe,SpecialChar,ImageButton,

	config.width = '100%';
	config.height = 600; //600px

};
