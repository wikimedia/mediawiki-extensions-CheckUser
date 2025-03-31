<template>
	<cdx-dialog
		v-model:open="open"
		class="ext-checkuser-ip-auto-reveal-off-dialog"
		:title="$i18n( 'checkuser-ip-auto-reveal-off-dialog-title' ).text()"
		:use-close-button="true"
		:default-action="defaultAction"
		:primary-action="primaryAction"
		@default="onExtend"
		@primary="onRemove"
	>
		<!-- eslint-disable vue/no-v-html -->
		<p v-html="expiry"></p>
		<template #footer-text>
			<p>{{ $i18n( 'checkuser-ip-auto-reveal-off-dialog-text-info' ).text() }}</p>
		</template>
	</cdx-dialog>
</template>

<script>
const { ref } = require( 'vue' );
const { CdxDialog } = require( '@wikimedia/codex' );
const { getAutoRevealStatus, setAutoRevealStatus } = require( './../ipRevealUtils.js' );
const { disableAutoReveal } = require( './../ipReveal.js' );

// @vue/component
module.exports = exports = {
	name: 'IPAutoRevealOffDialog',
	components: {
		CdxDialog
	},
	setup() {
		const open = ref( true );

		const defaultAction = {
			label: mw.message( 'checkuser-ip-auto-reveal-off-dialog-extend-action' ).text()
		};
		const primaryAction = ref( {
			label: mw.message( 'checkuser-ip-auto-reveal-off-dialog-off-action' ).text(),
			actionType: 'progressive'
		} );

		function onExtend() {
			const currentExpiryInSeconds = Number( getAutoRevealStatus() );
			const extendBySeconds = 10 * 60;

			let newRelativeExpiryInSeconds;
			if ( currentExpiryInSeconds === 0 ) {
				newRelativeExpiryInSeconds = extendBySeconds;
			} else {
				const newExpiryInSeconds = currentExpiryInSeconds + extendBySeconds;
				newRelativeExpiryInSeconds = newExpiryInSeconds - Math.round( Date.now() / 1000 );
			}

			setAutoRevealStatus( newRelativeExpiryInSeconds );

			open.value = false;
		}

		function onRemove() {
			disableAutoReveal();
			open.value = false;

			mw.notify( mw.message( 'checkuser-ip-auto-reveal-notification-off' ), {
				classes: [ 'ext-checkuser-ip-auto-reveal-notification-off' ],
				type: 'success'
			} );
		}

		return {
			open,
			defaultAction,
			primaryAction,
			onExtend,
			onRemove
		};
	},
	data() {
		const expiryTime = new Date( Number( getAutoRevealStatus() ) * 1000 );
		const secondsUntilExpiry = Math.round( ( expiryTime - Date.now() ) / 1000 );
		return {
			secondsUntilExpiry: secondsUntilExpiry,
			expiry: this.formatExpiryTime( secondsUntilExpiry )
		};
	},
	methods: {
		formatExpiryTime( secondsUntilExpiry ) {
			const hoursUntilExpiry = Math.floor( secondsUntilExpiry / 3600 );
			const minutesUntilExpiry = Math.floor( secondsUntilExpiry / 60 );

			const remainderMinutes = minutesUntilExpiry % 60;
			const remainderSeconds = secondsUntilExpiry % 60;

			const displayTime =
				String( hoursUntilExpiry ) + ':' +
				( remainderMinutes < 10 ? '0' : '' ) + String( remainderMinutes ) + ':' +
				( remainderSeconds < 10 ? '0' : '' ) + String( remainderSeconds );

			return mw.message( 'checkuser-ip-auto-reveal-off-dialog-text-expiry', displayTime ).parse();

		}
	},
	watch: {
		secondsUntilExpiry: {
			handler( expiry ) {
				if ( expiry > 0 && this.open ) {
					// Display the time until expiry. Note that this isn't perfectly in
					// sync with clock time.
					setTimeout( () => {
						this.secondsUntilExpiry--;
						this.expiry = this.formatExpiryTime( this.secondsUntilExpiry );
					}, 1000 );
				}
			},
			immediate: true
		}
	}
};
</script>
