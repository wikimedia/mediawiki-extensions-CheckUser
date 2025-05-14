<template>
	<cdx-menu-button
		v-model:selected="selection"
		:aria-label="ariaLabel"
		:menu-items="menuItems"
		:class="`ext-checkuser-userinfocard-menu-${ userId }`"
		@update:selected="onMenuSelect"
	>
		<cdx-icon :icon="cdxIconEllipsis"></cdx-icon>
	</cdx-menu-button>
</template>

<script>
const { ref } = require( 'vue' );
const { CdxMenuButton, CdxIcon } = require( '@wikimedia/codex' );
const { cdxIconEllipsis } = require( './icons.json' );

// @vue/component
module.exports = exports = {
	name: 'UserCardMenu',
	components: { CdxMenuButton, CdxIcon },
	props: {
		userId: {
			type: String,
			required: true
		},
		ariaLabel: {
			type: String,
			default: () => mw.msg( 'checkuser-userinfocard-open-menu-aria-label' )
		}
	},
	setup() {
		const selection = ref( null );
		const menuItems = [
			// TODO: T393946 Add proper links
			{
				label: mw.msg( 'checkuser-userinfocard-menu-view-contributions' ),
				value: 'view-contributions',
				link: '#'
			},
			{
				label: mw.msg( 'checkuser-userinfocard-menu-view-global-account' ),
				value: 'view-global-account',
				link: '#'
			},
			// TODO: T393981 Implement proper add/remove user page to watchlist
			{
				label: mw.msg( 'checkuser-userinfocard-menu-add-to-watchlist' ),
				value: 'add-watchlist',
				link: '#'
			},
			{
				label: mw.msg( 'checkuser-userinfocard-menu-check-ip' ),
				value: 'check-ip',
				link: '#'
			},
			{
				label: mw.msg( 'checkuser-userinfocard-menu-block-user' ),
				value: 'block-user',
				link: '#'
			},
			{
				label: mw.msg( 'checkuser-userinfocard-menu-turn-off' ),
				value: 'turn-off',
				link: mw.util.getUrl( 'Special:Preferences' ) + '#mw-prefsection-rendering'
			}
		];

		function onMenuSelect( value ) {
			const selectedItem = menuItems.find( ( item ) => item.value === value );
			if ( selectedItem && selectedItem.link ) {
				window.location.assign( selectedItem.link );
			}
			selection.value = null;
		}

		return {
			selection,
			menuItems,
			onMenuSelect,
			cdxIconEllipsis
		};
	}
};
</script>
