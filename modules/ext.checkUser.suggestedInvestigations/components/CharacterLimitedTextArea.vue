<template>
	<cdx-text-area
		v-bind="$attrs"
		v-model="computedTextContent"
	></cdx-text-area>
	<span
		v-if="remainingCharacters !== ''"
		class="ext-checkuser-dialog__textarea-character-count">
		{{ remainingCharacters }}
	</span>
</template>

<script>
const { CdxTextArea } = require( '@wikimedia/codex' );
const { computed, watch } = require( 'vue' );
const { codePointLength, trimCodePointLength } = require( 'mediawiki.String' );

// A Codex textarea with a character limit.
// @vue/component
module.exports = exports = {
	name: 'CharacterLimitedTextArea',
	components: {
		CdxTextArea
	},
	inheritAttrs: false,
	props: {
		/**
		 * The maximum number of Unicode code points accepted by this textarea.
		 */
		codePointLimit: { type: Number, required: true },
		/**
		 * The value of this text field.
		 * Must be bound with `v-model:text-content`.
		 */
		textContent: { type: String, required: true }
	},
	emits: [
		'update:text-content'
	],
	setup( props, ctx ) {
		const codePointLimit = props.codePointLimit;

		const computedTextContent = computed( {
			get: () => props.textContent,
			set: ( value ) => ctx.emit( 'update:text-content', value )
		} );

		const remainingCharacters = computed( () => {
			if ( computedTextContent.value === '' ) {
				return '';
			}

			const remaining = codePointLimit - codePointLength( computedTextContent.value );

			// Only show the character counter as the user is approaching the limit,
			// to avoid confusion stemming from our definition of a character not matching
			// the user's own expectations of what counts as a character.
			// This is consistent with other features such as VisualEditor.
			if ( remaining > 99 ) {
				return '';
			}

			return mw.language.convertNumber( remaining );
		} );

		watch( computedTextContent, () => {
			if ( codePointLength( computedTextContent.value ) > codePointLimit ) {
				const { newVal } = trimCodePointLength( '', computedTextContent.value, codePointLimit );
				computedTextContent.value = newVal;
			}
		} );

		return {
			computedTextContent,
			remainingCharacters
		};
	}
};
</script>

<style lang="less">
@import ( reference ) 'mediawiki.skin.variables.less';

.ext-checkuser-dialog__textarea-character-count {
	color: @color-subtle;
	float: right;
}
</style>
