<template>
	<div class="ext-checkuser-userinfocard-body">
		<p>Joined: {{ joinedDate }} ({{ joinedRelative }})</p>
		<!-- Placeholder for future content: blocks, groups, links, etc. -->
	</div>
</template>

<script>

// @vue/component
module.exports = exports = {
	name: 'UserCard',
	components: {},
	props: {
		joinedDate: String,
		joinedRelative: String
	}
};
</script>

<style>
.ext-checkuser-userinfocard-body {
	padding: 0;
}

</style>
