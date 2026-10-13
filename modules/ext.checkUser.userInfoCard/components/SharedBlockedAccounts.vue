<template>
	<div class="ext-checkuser-userinfocard-sharedblocked">
		<template v-if="result">
			<info-row-with-links
				v-for="row in rows"
				:key="row.messageKey"
				:icon="cdxIconUserBlocked"
				:icon-class="row.iconClass"
				:message-key="row.messageKey"
				:main-value="row.mainValue"
				:main-link="row.mainLink"
				:main-link-log-id="row.logId"
				:username="username"
			></info-row-with-links>
		</template>
		<info-row
			v-else-if="error"
			:icon="cdxIconAlert"
			icon-class="ext-checkuser-userinfocard-icon ext-checkuser-userinfocard-icon-blocks"
		>
			{{ error }}
		</info-row>
		<!--
			The tooltip directive adds its element to the parent of the button.
			This wrapper keeps that element out of the v-if chain, so that Vue can replace the button.
		-->
		<div v-else>
			<cdx-button
				v-tooltip="$i18n( 'checkuser-userinfocard-sharedblocked-button-tooltip' ).text()"
				action="progressive"
				weight="quiet"
				size="small"
				class="ext-checkuser-userinfocard-sharedblocked-button"
				:disabled="loading"
				@click="onClick"
			>
				{{ $i18n( 'checkuser-userinfocard-sharedblocked-button' ).text() }}
			</cdx-button>
		</div>
	</div>
</template>

<script>
const { computed, ref } = require( 'vue' );
const { CdxButton, CdxTooltip } = require( '../codex.js' );
const { cdxIconAlert, cdxIconUserBlocked } = require( './icons.json' );
const InfoRow = require( './InfoRow.vue' );
const InfoRowWithLinks = require( './InfoRowWithLinks.vue' );
const { getSharedBlockedAccounts } = require( '../rest.js' );

// @vue/component
module.exports = exports = {
	name: 'SharedBlockedAccounts',
	components: { CdxButton, InfoRow, InfoRowWithLinks },
	directives: {
		tooltip: CdxTooltip
	},
	props: {
		username: {
			type: String,
			required: true
		}
	},
	setup( props ) {
		const loading = ref( false );
		const error = ref( null );
		const result = ref( null );
		const checkUserLink = mw.Title.makeTitle( -1, 'CheckUser' ).getUrl(
			{ user: props.username }
		);

		function formatCount( data ) {
			const count = mw.language.convertNumber( data.count );
			return data.complete ?
				count :
				mw.msg( 'checkuser-userinfocard-sharedblocked-count-incomplete', count );
		}

		function makeRow( messageKey, logId, data ) {
			return {
				messageKey,
				logId,
				mainValue: formatCount( data ),
				mainLink: data.count > 0 ? checkUserLink : '',
				iconClass: data.count > 0 ?
					'ext-checkuser-userinfocard-icon ext-checkuser-userinfocard-icon-blocks' :
					'ext-checkuser-userinfocard-icon'
			};
		}

		const rows = computed( () => result.value ? [
			makeRow(
				'checkuser-userinfocard-sharedblocked-ip',
				'shared_blocked_ip',
				result.value.ip
			),
			makeRow(
				'checkuser-userinfocard-sharedblocked-browser-version',
				'shared_blocked_browser_version',
				result.value.browserVersion
			)
		] : [] );

		function onClick() {
			loading.value = true;
			getSharedBlockedAccounts( props.username ).then(
				( data ) => {
					result.value = data;
					loading.value = false;
				},
				( err, errObject ) => {
					const responseJSON = ( errObject && errObject.xhr && errObject.xhr.responseJSON ) || {};
					const userLang = mw.config.get( 'wgUserLanguage' );
					error.value = (
						responseJSON.messageTranslations &&
						responseJSON.messageTranslations[ userLang ]
					) || mw.msg( 'checkuser-userinfocard-error-generic' );
					loading.value = false;
				}
			);
		}

		return {
			loading,
			error,
			result,
			rows,
			onClick,
			cdxIconAlert,
			cdxIconUserBlocked
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-userinfocard-sharedblocked {
	margin-bottom: @spacing-25;
}

.ext-checkuser-userinfocard-sharedblocked-button {
	margin-left: calc( @size-100 + @spacing-25 );
}
</style>
