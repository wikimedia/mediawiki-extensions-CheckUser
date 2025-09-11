<template>
	<cdx-dialog
		v-model:open="open"
		:title="$i18n( 'checkuser-suggestedinvestigations-change-status-dialog-title' ).text()"
		:close-button-label="$i18n(
			'checkuser-suggestedinvestigations-change-status-dialog-close-label'
		).text()"
		:use-close-button="true"
		class="ext-checkuser-suggestedinvestigations-change-status-dialog"
	>
		<p class="ext-checkuser-suggestedinvestigations-change-status-dialog-description">
			{{ $i18n( 'checkuser-suggestedinvestigations-change-status-dialog-text' ).text() }}
		</p>
		<div class="ext-checkuser-suggestedinvestigations-change-status-dialog-footer">
			<cdx-button
				class="
					ext-checkuser-suggestedinvestigations-change-status-dialog-footer__cancel-btn
				"
				@click="onCancelButtonClick"
			>
				{{ $i18n(
					'checkuser-suggestedinvestigations-change-status-dialog-cancel-btn'
				).text() }}
			</cdx-button>
			<cdx-button
				class="
					ext-checkuser-suggestedinvestigations-change-status-dialog-footer__submit-btn
				"
				weight="primary"
				action="progressive"
			>
				{{ $i18n(
					'checkuser-suggestedinvestigations-change-status-dialog-submit-btn'
				).text() }}
			</cdx-button>
		</div>
	</cdx-dialog>
</template>

<script>
const { ref } = require( 'vue' ),
	{ CdxButton, CdxDialog } = require( '@wikimedia/codex' );

// @vue/component
module.exports = exports = {
	name: 'ChangeInvestigationStatusDialog',
	components: {
		CdxButton,
		CdxDialog
	},
	setup() {
		const open = ref( true );

		function onCancelButtonClick() {
			open.value = false;
		}

		return {
			open,
			onCancelButtonClick
		};
	}
};
</script>

<style lang="less">
@import ( reference ) 'mediawiki.skin.variables.less';

.ext-checkuser-suggestedinvestigations-change-status-dialog {
	.ext-checkuser-suggestedinvestigations-change-status-dialog-footer {
		float: right;
		margin-top: @spacing-100;

		.ext-checkuser-suggestedinvestigations-change-status-dialog-footer__cancel-btn {
			margin-right: @spacing-50;
		}
	}
}
</style>
