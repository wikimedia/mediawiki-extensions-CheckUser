var InvestigateMenuSelectWidget = require( './InvestigateMenuSelectWidget.js' );

/**
 * Investigate Button Menu Select Widget
 *
 * Inherits from OO.ui.ButtonMenuSelectWidget but uses as its menu an
 * InvestigateMenuSelectWidget, allowing control over UI handling.
 *
 * @class
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */

var InvestigateButtonMenuSelectWidget = function ( config ) {
	// Parent constructor
	InvestigateButtonMenuSelectWidget.super.call( this, config );

	// Override the menu added by the parent
	this.menu = new InvestigateMenuSelectWidget( $.extend( {
		widget: this,
		$floatableContainer: this.$element
	}, config.menu ) );
	this.getMenu().connect( this, {
		select: 'onMenuSelect',
		toggle: 'onMenuToggle'
	} );
	this.$overlay.append( this.menu.$element );
};

/* Setup */

OO.inheritClass( InvestigateButtonMenuSelectWidget, OO.ui.ButtonMenuSelectWidget );

module.exports = InvestigateButtonMenuSelectWidget;
