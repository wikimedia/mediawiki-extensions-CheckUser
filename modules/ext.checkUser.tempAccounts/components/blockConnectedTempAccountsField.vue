<template>
	<!-- Hide feature if no connected accounts were found on a successful call.
	Check for the error state because in that case, even if no connected accounts were
	found, the feature should still be visible. -->
	<div
		v-if="visible &&
			( connectedTempAccounts.length || blockConnectedTempAccountsApiCallError )"
		class="ext-checkuser-tempaccount-specialblock-connectedaccounts"
	>
		<cdx-message
			v-if="blockConnectedTempAccountsApiCallError"
			type="warning"
		>
			{{ blockConnectedTempAccountsApiCallError }}
		</cdx-message>
		<!-- eslint-disable vue/no-v-html-->
		<p v-if="!blockConnectedTempAccountsApiCallError">
			<span
				v-if="connectedTempAccountsText"
				v-html="connectedTempAccountsText"
			>
			</span>
			<span
				v-i18n-html:checkuser-related-tas-specialblock-contribs-link="[ targetUser ]"
			>
			</span>
		</p>
		<!-- eslint-enable vue/no-v-html-->
		<cdx-checkbox
			v-if="!blockId"
			v-model="shouldBlockConnectedTempAccounts"
			name="block-connected-accounts"
			:disabled="!!blockConnectedTempAccountsApiCallError || tooManyAccountsToBlock"
		>
			{{ $i18n(
				'checkuser-related-tas-specialblock-checkbox-label'
			).text() }}
		</cdx-checkbox>
		<cdx-message
			v-if="shouldBlockConnectedTempAccounts"
			type="warning"
		>
			<span
				v-i18n-html:checkuser-related-tas-specialblock-confirm="[
					connectedTempAccounts.length
				]"
			>
			</span>
		</cdx-message>
		<cdx-message
			v-if="tooManyAccountsToBlock"
			type="warning"
		>
			<span
				v-i18n-html:checkuser-related-tas-specialblock-limit-exceeded-error="[
					maxAllowed
				]"
			>
			</span>
		</cdx-message>
	</div>
</template>

<script>
const { computed, defineComponent, ref, watch } = require( 'vue' ),
	{ CdxCheckbox, CdxMessage } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'BlockConnectedTempAccountsField',
	components: {
		CdxCheckbox,
		CdxMessage
	},
	props: {
		targetUser: { type: [ String, null ], required: true },
		blockId: { type: [ Number, null ], required: true }
	},
	setup( props ) {
		// Limit bulk blocking to 15 accounts, covering the vast majority of cases.
		// See T419526#11731721.
		const maxAllowed = 15;

		const visible = computed( () => mw.config.get( 'blockEnableMultiblocks' ) &&
			mw.config.get( 'wgTemporaryAccountIPRevealAllowed' ) &&
			mw.util.isTemporaryUser( props.targetUser ) );
		const connectedTempAccounts = ref( [] );
		const blockConnectedTempAccountsApiCallError = ref( '' );
		const connectedTempAccountsText = ref( '' );
		const shouldBlockConnectedTempAccounts = ref( false );
		const tooManyAccountsToBlock = ref( false );
		function resetForm() {
			blockConnectedTempAccountsApiCallError.value = '';
			shouldBlockConnectedTempAccounts.value = false;
			tooManyAccountsToBlock.value = false;
		}

		watch( () => props.targetUser, async ( newTarget, currentTarget ) => {
			if ( newTarget === currentTarget ) {
				return;
			}

			// Reset state on new call
			connectedTempAccounts.value = [];
			connectedTempAccountsText.value = '';
			resetForm();

			// Do nothing if the input won't be visible
			if ( !visible.value ) {
				return;
			}

			// Get all connected accounts
			try {
				const { connectedAccounts, ipsUsedCount } = await new mw.Rest().post(
					`/checkuser/v0/connectedtemporaryaccounts/${ props.targetUser }`,
					{ token: mw.user.tokens.get( 'csrfToken' ) }
				);
				connectedTempAccounts.value = connectedAccounts
					.filter( ( user ) => props.targetUser !== user );

				if ( connectedTempAccounts.value.length > maxAllowed ) {
					tooManyAccountsToBlock.value = true;
				}

				const userLinks = connectedTempAccounts.value.map(
					( target ) => mw.message( 'checkuser-related-tas-target', target ).parse()
				);
				const listOfUserLinks = mw.language.listToText( userLinks );
				connectedTempAccountsText.value = mw.message(
					'checkuser-related-tas-specialblock-accounts-list',
					connectedTempAccounts.value.length,
					ipsUsedCount,
					props.targetUser,
					mw.config.get( 'wgCUDMaxAge' ) / ( 60 * 60 * 24 ),
					listOfUserLinks
				);
			} catch ( e ) {
				blockConnectedTempAccountsApiCallError.value = mw.msg(
					'checkuser-related-tas-specialblock-accounts-list-error',
					[ props.targetUser, mw.user ]
				);
			}
		}, { immediate: true } );

		mw.hook( 'mw.special.block.doBlockParamsReady' ).add( ( params ) => {
			if ( shouldBlockConnectedTempAccounts.value ) {
				params.blockConnectedTempAccounts = connectedTempAccounts.value;
			}
		} );

		mw.hook( 'mw.special.block.formReset' ).add( () => {
			resetForm();
		} );

		return {
			blockConnectedTempAccountsApiCallError,
			connectedTempAccounts,
			connectedTempAccountsText,
			maxAllowed,
			shouldBlockConnectedTempAccounts,
			tooManyAccountsToBlock,
			visible
		};
	}
} );
</script>
