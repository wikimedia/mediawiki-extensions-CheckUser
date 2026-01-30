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
			class="ext-checkuser-suggestedinvestigations-filter-dialog-signal-filter"
		>
			<template #label>
				{{ $i18n(
					'checkuser-suggestedinvestigations-filter-dialog-signal-filter-header'
				).text() }}
			</template>
			<cdx-checkbox
				v-for="checkbox in signalCheckboxes"
				:key="checkbox.urlName"
				v-model="checkbox.isChecked"
				:name="'filter-signal-' + checkbox.urlName"
			>
				{{ checkbox.label }}
			</cdx-checkbox>
		</cdx-field>
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
		<cdx-field
			class="ext-checkuser-suggestedinvestigations-filter-dialog-account-activity-filter"
		>
			<template #label>
				{{ $i18n(
					'checkuser-suggestedinvestigations-filter-dialog-account-activity-header'
				).text() }}
			</template>
			<cdx-checkbox
				v-model="hideCasesWithNoUserEditsCheckboxValue"
				name="filter-hide-cases-with-no-user-edits"
			>
				{{ hideCasesWithNoUserEditsCheckboxLabel }}
			</cdx-checkbox>
		</cdx-field>
		<filter-dialog-username-filter v-model:selected-usernames="selectedUsernames">
		</filter-dialog-username-filter>
	</cdx-dialog>
</template>

<script>
const { ref } = require( 'vue' ),
	{ CdxDialog, CdxField, CdxCheckbox, CdxInfoChip } = require( '@wikimedia/codex' ),
	Constants = require( '../Constants.js' ),
	{ caseStatusToChipStatus, updateFiltersOnPage } = require( '../utils.js' ),
	FilterDialogUsernameFilter = require( './FilterDialogUsernameFilter.vue' );

// @vue/component
module.exports = exports = {
	name: 'FilterDialog',
	components: {
		CdxDialog,
		CdxField,
		CdxCheckbox,
		CdxInfoChip,
		FilterDialogUsernameFilter
	},
	props: {
		/**
		 * A dictionary describing what filters are active on the current page
		 * which is the value of the JS config var
		 * `wgCheckUserSuggestedInvestigationsActiveFilters`.
		 *
		 * Requires the following keys:
		 *  - status: An array of statuses that are being filtered for on the page
		 *  - username: An array of usernames that are being filtered for
		 *  - hideCasesWithNoUserEdits: Boolean. If true, only show cases where at least one
		 *      of the accounts has made an edit
		 *  - signal: An array of signals that are being filtered for on the page
		 */
		initialFilters: {
			type: Object,
			required: true
		}
	},
	setup( props ) {
		const open = ref( true );

		const signals = mw.config.get( 'wgCheckUserSuggestedInvestigationsSignals' );
		const signalCheckboxes = ref( signals.map( ( signal ) => {
			let signalDisplayName;
			let urlName;
			let signalName;

			if ( typeof signal !== 'string' ) {
				signalName = signal.name;

				if ( signal.urlName ) {
					urlName = signal.urlName;
				} else {
					urlName = signal.name;
				}

				if ( signal.displayName ) {
					signalDisplayName = signal.displayName;
				} else {
					// For grepping, the currently known signal messages are:
					// * checkuser-suggestedinvestigations-signal-dev-signal-1
					// * checkuser-suggestedinvestigations-signal-dev-signal-2
					signalDisplayName = mw.msg( 'checkuser-suggestedinvestigations-signal-' + signal.name );
				}
			} else {
				urlName = signalName = signal;
				// For grepping, the currently known signal messages are:
				// * checkuser-suggestedinvestigations-signal-dev-signal-1
				// * checkuser-suggestedinvestigations-signal-dev-signal-2
				signalDisplayName = mw.msg( 'checkuser-suggestedinvestigations-signal-' + signal );
			}

			return {
				urlName: urlName,
				label: signalDisplayName,
				isChecked: props.initialFilters.signal.includes( signalName )
			};
		} ) );

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

		const selectedUsernames = ref( props.initialFilters.username );

		let noUserEditsCheckboxLabelMsgKey =
			'checkuser-suggestedinvestigations-filter-dialog-hide-cases-with-no-user-edits';
		if ( mw.config.get( 'wgCheckUserSuggestedInvestigationsGlobalEditCountsUsed' ) ) {
			noUserEditsCheckboxLabelMsgKey += '-globally';
		}

		// Uses:
		// * checkuser-suggestedinvestigations-filter-dialog-hide-cases-with-no-user-edits
		// * checkuser-suggestedinvestigations-filter-dialog-hide-cases-with-no-user-edits-globally
		const hideCasesWithNoUserEditsCheckboxLabel = mw.msg( noUserEditsCheckboxLabelMsgKey );

		const hideCasesWithNoUserEditsCheckboxValue = ref(
			props.initialFilters.hideCasesWithNoUserEdits
		);

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

			const selectedSignals = signalCheckboxes.value.filter(
				( signalData ) => signalData.isChecked
			);

			const filters = {
				status: selectedStatuses.map( ( statusData ) => statusData.value ),
				username: selectedUsernames.value,
				signal: selectedSignals.map( ( signalData ) => signalData.urlName )
			};

			if ( hideCasesWithNoUserEditsCheckboxValue.value ) {
				filters.hideCasesWithNoUserEdits = 1;
			}

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
			selectedUsernames,
			statusCheckboxes,
			signalCheckboxes,
			hideCasesWithNoUserEditsCheckboxLabel,
			hideCasesWithNoUserEditsCheckboxValue,
			onCloseButtonClick,
			onShowResultsButtonClick
		};
	}
};
</script>
