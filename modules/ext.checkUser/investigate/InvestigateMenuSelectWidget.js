/**
 * Investigate Menu Select Widget
 *
 * @class
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */

var InvestigateMenuSelectWidget = function ( config ) {
	// Parent constructor
	InvestigateMenuSelectWidget.super.call( this, config );
};

/* Setup */

OO.inheritClass( InvestigateMenuSelectWidget, OO.ui.MenuSelectWidget );

/**
 * @inheritdoc
 */
InvestigateMenuSelectWidget.prototype.onDocumentKeyDown = function ( e ) {
	var selected = this.findSelectedItems(),
		currentItem = this.findHighlightedItem() || (
			Array.isArray( selected ) ? selected[ 0 ] : selected
		);

	if ( e.keyCode === OO.ui.Keys.ENTER ) {
		this.emit( 'investigate', currentItem );
	}

	return InvestigateMenuSelectWidget.super.prototype.onDocumentKeyDown.call( this, e );
};

module.exports = InvestigateMenuSelectWidget;
