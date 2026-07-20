<template>
	<div v-if="visible">
		<cdx-message
			type="warning"
			class="mw-checkuser-si-relatedaccounts-message"
		>
			<span
				v-i18n-html:checkuser-si-related-cases-info="[
					relatedAccountsCount,
					associatedCasesCount,
					siUrl
				]"
			>
			</span>
		</cdx-message>
	</div>
</template>

<script>
const { computed, defineComponent, ref, watch } = require( 'vue' ),
	{ CdxMessage } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'RelatedCasesMessage',
	components: {
		CdxMessage
	},
	props: {
		targetUser: { type: [ String, null ], required: true }
	},
	setup( props ) {
		const relatedAccountsCount = ref( 0 );
		const associatedCasesCount = ref( 0 );
		const apiError = ref( false );
		const api = new mw.Rest();

		// Build the SI link in JS so usernames containing '+' or '&' are encoded correctly
		const siUrl = computed( () => mw.util.getUrl(
			'Special:SuggestedInvestigations',
			{ username: props.targetUser }
		) );

		// This message should only be visible if:
		// - The user has permission to view suggested investigations
		// - The target has SI cases associated with it
		const canView = mw.config.get( 'wgCheckUserSuggestedInvestigationsEnabled' ) &&
			mw.config.get( 'wgCheckUserCanViewSuggestedInvestigations' );
		const visible = computed(
			() => canView &&
			relatedAccountsCount.value &&
			associatedCasesCount.value &&
			!apiError.value
		);

		function resetForm() {
			relatedAccountsCount.value = 0;
			associatedCasesCount.value = 0;
			apiError.value = false;
		}
		watch( () => props.targetUser, async ( newTarget, currentTarget ) => {
			// Do nothing if:
			// - User cannot see this feature
			// - Target does not exist
			// - Target is same as current target
			if ( !canView || !newTarget || newTarget === currentTarget ) {
				return;
			}

			// If there's a call in-flight, abort it since the target has changed
			api.abort();
			resetForm();

			// Instrument that a user's related cases can be looked up
			mw.track( 'stats.mediawiki_checkuser_suggestedinvestigations_specialblock_relatedcases_total', 1, {
				action: 'lookup'
			} );
			await api
				.post(
					`/checkuser/v0/suggestedinvestigationsusersummary/${ encodeURIComponent( props.targetUser ) }`,
					{ token: mw.user.tokens.get( 'csrfToken' ) }
				).then(
					( data ) => {
						relatedAccountsCount.value = data.relatedUserIdsCount;
						associatedCasesCount.value = data.relatedCasesCount;

						// Instrument that matches were found
						if ( data.relatedUserIdsCount || data.relatedCasesCount ) {
							mw.track(
								'stats.mediawiki_checkuser_suggestedinvestigations_specialblock_relatedcases_total',
								1,
								{
									action: 'hit',
									relateduserscount: data.relatedUserIdsCount,
									relatedcasescount: data.relatedCasesCount
								}
							);
						}
					}
				).catch(
					( code, error ) => {
						// Manually aborted stale call
						if ( code === 'http' && error.exception === 'abort' ) {
							mw.track(
								'stats.mediawiki_checkuser_suggestedinvestigations_specialblock_relatedcases_total',
								1,
								{
									action: 'abort'
								}
							);
							return;
						}

						// Don't show the user anything if something has gone wrong as it's not actionable.
						apiError.value = true;

						mw.track( 'stats.mediawiki_checkuser_suggestedinvestigations_specialblock_relatedcases_total', 1, {
							action: 'apierror'
						} );
					}
				);

		}, { immediate: true } );

		mw.hook( 'mw.special.block.formReset' ).add( () => {
			resetForm();
		} );

		return {
			visible,
			relatedAccountsCount,
			associatedCasesCount,
			siUrl
		};
	}
} );
</script>

<style lang="less">
@import ( reference ) 'mediawiki.skin.variables.less';

.mw-checkuser-si-relatedaccounts-message {
	margin-top: 1rem;
}
</style>
