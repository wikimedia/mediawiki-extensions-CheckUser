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
const { ref, computed } = require( 'vue' );
const { CdxMenuButton, CdxIcon } = require( '@wikimedia/codex' );
const { cdxIconEllipsis } = require( './icons.json' );
const useWatchList = require( '../composables/useWatchList.js' );

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
		},
		userPageWatched: {
			type: Boolean,
			default: false
		}
	},
	setup( props ) {
		const selection = ref( null );
		const {
			toggleWatchList,
			watchListLabel
		} = useWatchList( props.username, props.userPageWatched );
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

		// Computed is necessary for the watchListLabel item
		const menuItems = computed( () => [
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
			{
				label: watchListLabel.value,
				value: 'toggle-watchlist'
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
		] );

		function onMenuSelect( value ) {
			const selectedItem = menuItems.value.find( ( item ) => item.value === value );
			if ( value === 'toggle-watchlist' ) {
				toggleWatchList();
			} else if ( selectedItem && selectedItem.link ) {
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
