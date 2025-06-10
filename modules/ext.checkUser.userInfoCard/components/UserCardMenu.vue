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
			type: [ String, Number ],
			required: true
		},
		username: {
			type: String,
			required: true
		},
		ariaLabel: {
			type: String,
			default: () => mw.msg( 'checkuser-userinfocard-open-menu-aria-label' )
		}
	},
	setup( props ) {
		const selection = ref( null );
		const contributionsLink = mw.Title.makeTitle(
			-1, `Contributions/${ props.username }`
		).getUrl();
		const globalAccountLink = mw.Title.makeTitle(
			-1, `CentralAuth/${ props.username }`
		).getUrl();
		const checkUserLink = mw.Title.makeTitle( -1, 'CheckUser' ).getUrl(
			{ user: props.username }
		);
		const blockUserLink = mw.Title.makeTitle(
			-1, `Block/${ props.username }`
		).getUrl();
		const turnOffLink = mw.Title.makeTitle(
			-1, 'Special:Preferences'
		).getUrl() + '#mw-prefsection-rendering-advancedrendering';
		const menuItems = [
			{
				label: mw.msg( 'checkuser-userinfocard-menu-view-contributions' ),
				value: 'view-contributions',
				link: contributionsLink
			},
			{
				label: mw.msg( 'checkuser-userinfocard-menu-view-global-account' ),
				value: 'view-global-account',
				link: globalAccountLink
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
				link: checkUserLink
			},
			{
				label: mw.msg( 'checkuser-userinfocard-menu-block-user' ),
				value: 'block-user',
				link: blockUserLink
			},
			{
				label: mw.msg( 'checkuser-userinfocard-menu-turn-off' ),
				value: 'turn-off',
				link: turnOffLink
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
