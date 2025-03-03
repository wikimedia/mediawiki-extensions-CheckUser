<template>
	<div v-if="visible">
		<div
			v-if="message"
			class="ext-checkuser-tempaccount-specialblock-ips"
		>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<label v-html="message"></label>
		</div>
		<cdx-button
			v-else
			action="progressive"
			weight="quiet"
			class="ext-checkuser-tempaccount-specialblock-ips-link"
			@click="onClick"
		>
			{{ $i18n( 'checkuser-tempaccount-reveal-ip-button-label' ).text() }}
		</cdx-button>
	</div>
</template>

<script>
const { computed, defineComponent, ref, watch } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const { performFullRevealRequest } = require( './rest.js' );

module.exports = exports = defineComponent( {
	name: 'ShowIPButton',
	components: { CdxButton },
	props: {
		targetUser: { type: [ String, null ], required: true }
	},
	setup( props ) {
		const visible = computed( () => mw.util.isTemporaryUser( props.targetUser ) );
		const message = ref( '' );

		watch( () => props.targetUser, () => {
			message.value = '';
		} );

		/**
		 * Handle the click event.
		 */
		function onClick() {
			performFullRevealRequest( props.targetUser, [], [] )
				.then( ( { ips } ) => {
					if ( ips.length ) {
						const ipLinks = ips.map( ( ip ) => {
							const a = document.createElement( 'a' );
							a.href = mw.util.getUrl( `Special:IPContributions/${ ip }` );
							a.textContent = ip;
							return a.outerHTML;
						} );
						message.value = mw.message(
							'checkuser-tempaccount-specialblock-ips',
							ipLinks.length,
							mw.language.listToText( ipLinks )
						).text();
					} else {
						message.value = mw.message(
							'checkuser-tempaccount-no-ip-results',
							Math.round( mw.config.get( 'wgCUDMaxAge' ) / 86400 )
						).text();
					}
				} )
				.catch( () => {
					message.value = mw.message( 'checkuser-tempaccount-reveal-ip-error' ).text();
				} );
		}

		return {
			visible,
			message,
			onClick
		};
	}
} );
</script>
