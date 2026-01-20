<template>
	<cdx-dialog
		v-model:open="open"
		:title="$i18n( 'checkuser-suggestedinvestigations-filter-dialog-title' ).text()"
		:close-button-label="$i18n(
			'checkuser-suggestedinvestigations-filter-dialog-close-button'
		).text()"
		:use-close-button="true"
		class="ext-checkuser-suggestedinvestigations-filter-dialog"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		@primary="onShowResultsButtonClick"
		@default="onCloseButtonClick"
	>
		<cdx-field
			class="ext-checkuser-suggestedinvestigations-filter-dialog-status-filter"
		>
			<template #label>
				{{ $i18n(
					'checkuser-suggestedinvestigations-filter-dialog-status-filter-header'
				).text() }}
			</template>
			<cdx-checkbox
				v-for="checkbox in statusCheckboxes"
				:key="checkbox.value"
				v-model="checkbox.isChecked"
				:name="'filter-status-' + checkbox.value"
			>
				<cdx-info-chip :status="checkbox.status">
					{{ checkbox.label }}
				</cdx-info-chip>
			</cdx-checkbox>
		</cdx-field>
	</cdx-dialog>
</template>

<script>
const { ref } = require( 'vue' ),
	{ CdxDialog, CdxField, CdxCheckbox, CdxInfoChip } = require( '@wikimedia/codex' ),
	Constants = require( '../Constants.js' ),
	{ caseStatusToChipStatus, updateFiltersOnPage } = require( '../utils.js' );

// @vue/component
module.exports = exports = {
	name: 'FilterDialog',
	components: {
		CdxDialog,
		CdxField,
		CdxCheckbox,
		CdxInfoChip
	},
	props: {
		/**
		 * A dictionary describing what filters are active on the current page
		 * which is the value of the JS config var
		 * `wgCheckUserSuggestedInvestigationsActiveFilters`.
		 *
		 * Requires the following keys:
		 *  - status: An array of statuses that are being filtered for on the page
		 */
		initialFilters: {
			type: Object,
			required: true
		}
	},
	setup( props ) {
		const open = ref( true );

		const statusCheckboxes = ref( Constants.caseStatuses.map( ( status ) => ( {
			value: status,
			// Uses:
			// * checkuser-suggestedinvestigations-status-open
			// * checkuser-suggestedinvestigations-status-resolved
			// * checkuser-suggestedinvestigations-status-invalid
			label: mw.msg( 'checkuser-suggestedinvestigations-status-' + status ),
			status: caseStatusToChipStatus( status ),
			isChecked: props.initialFilters.status.includes( status )
		} ) ) );

		function onCloseButtonClick() {
			open.value = false;
		}

		/**
		 * Handles a click of the "Show results" button which
		 * causes the page to be reloaded with the selected filters applied
		 */
		function onShowResultsButtonClick() {
			const selectedStatuses = statusCheckboxes.value.filter(
				( statusData ) => statusData.isChecked
			);

			const filters = {
				status: selectedStatuses.map( ( statusData ) => statusData.value )
			};

			updateFiltersOnPage( filters, window );
		}

		const primaryAction = {
			label: mw.msg( 'checkuser-suggestedinvestigations-filter-dialog-show-results-button' ),
			actionType: 'progressive'
		};

		const defaultAction = {
			label: mw.msg( 'checkuser-suggestedinvestigations-filter-dialog-close-button' )
		};

		return {
			open,
			primaryAction,
			defaultAction,
			statusCheckboxes,
			onCloseButtonClick,
			onShowResultsButtonClick
		};
	}
};
</script>
