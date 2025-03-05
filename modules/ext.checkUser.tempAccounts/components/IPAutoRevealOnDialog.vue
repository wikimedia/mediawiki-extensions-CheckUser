<template>
	<cdx-dialog
		v-model:open="open"
		class="ext-checkuser-ip-auto-reveal-on-dialog"
		:title="$i18n( 'checkuser-ip-auto-reveal-on-dialog-title' ).text()"
		:use-close-button="true"
		:default-action="defaultAction"
		:primary-action="primaryAction"
		@default="open = false"
		@primary="onSubmit"
	>
		<p>
			{{ $i18n( 'checkuser-ip-auto-reveal-on-dialog-text' ).text() }}
		</p>
		<cdx-field>
			<template #label>
				{{ $i18n( 'checkuser-ip-auto-reveal-on-dialog-select-label' ).text() }}
			</template>
			<cdx-select
				v-model:selected="selected"
				class="ext-checkuser-ip-auto-reveal-on-dialog__select"
				:menu-items="menuItems"
				:default-label="$i18n( 'checkuser-ip-auto-reveal-on-dialog-select-default' ).text()"
				@update:selected="onChange"
			>
			</cdx-select>
		</cdx-field>
	</cdx-dialog>
</template>

<script>
const { ref } = require( 'vue' );
const { CdxDialog, CdxField, CdxSelect } = require( '@wikimedia/codex' );
const { setAutoRevealStatus } = require( './../ipRevealUtils.js' );

// @vue/component
module.exports = exports = {
	name: 'IPAutoRevealOnDialog',
	components: {
		CdxDialog,
		CdxField,
		CdxSelect
	},
	setup() {
		const open = ref( true );
		const selected = ref( null );

		const defaultAction = {
			label: mw.message( 'checkuser-ip-auto-reveal-on-dialog-default-action' ).text()
		};
		const primaryAction = ref( {
			label: mw.message( 'checkuser-ip-auto-reveal-on-dialog-primary-action' ).text(),
			actionType: 'progressive',
			disabled: !selected.value
		} );

		const menuItems = [
			{
				label: mw.message( 'checkuser-ip-auto-reveal-on-dialog-select-duration-1800' ).text(),
				value: '1800'
			},
			{
				label: mw.message( 'checkuser-ip-auto-reveal-on-dialog-select-duration-3600' ).text(),
				value: '3600'
			}
		];

		function onChange() {
			primaryAction.value.disabled = !selected.value;
		}

		function onSubmit() {
			setAutoRevealStatus( selected.value );
			window.location.reload();
		}

		return {
			open,
			defaultAction,
			primaryAction,
			menuItems,
			selected,
			onChange,
			onSubmit
		};
	}
};
</script>
