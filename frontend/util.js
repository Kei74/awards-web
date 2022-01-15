/* eslint-disable eqeqeq */
/* eslint-disable no-alert */

function slugify (text) {
	return text.toLowerCase().replace(/\s+/g, '-');
}

async function makeQuery (query, idArr) {
	const response = await fetch('https://graphql.anilist.co', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'Accept': 'application/json',
		},
		mode: 'no-cors',
		body: JSON.stringify({
			query,
			variables: {
				id: idArr,
			},
		}),
	});
	if (!response.ok) {
		console.log(response);
		return false;
	}
	const data = await response.json();
	return data.data.Page.results; // bad hardcode for the bad function
}

async function paginatedQuery (query, idArr, page) {
	const response = await fetch('https://graphql.anilist.co', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'Accept': 'application/json',
		},
		body: JSON.stringify({
			query,
			variables: {
				id: idArr,
				page,
				perPage: 50,
			},
		}),
	});
	if (!response.ok) {
		return false;
	}
	const data = await response.json();
	return data; // bad hardcode for the bad function
}

async function userPaginatedQuery (query, user, page) {
	const response = await fetch('https://graphql.anilist.co', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'Accept': 'application/json',
		},
		body: JSON.stringify({
			query,
			variables: {
				userId: user,
				page,
			},
		}),
	});
	if (!response.ok) {
		return false;
	}
	const data = await response.json();
	return data; // bad hardcode for the bad function
}

// Begin stuff that got imported from the old public voting site and hasn't been used yet

// Returns true if the first string is roughly included in the second string.
function fuzzyMatch (typing, target) {
	return target != undefined && target.toLowerCase().includes(typing.toLowerCase());
}

// Returns true if a string is a partial match for at least one member of an array.
function stringMatchesArray (str, arr) {
	return arr.some(val => fuzzyMatch(str, val));
}

// https://stackoverflow.com/a/6274381/1070107
function shuffle (a) {
	let j; let x; let i;
	for (i = a.length - 1; i > 0; i--) {
		j = Math.floor(Math.random() * (i + 1));
		x = a[i];
		a[i] = a[j];
		a[j] = x;
	}
	return a;
}


function submit (url, data) {
	return fetch(url, {
		method: 'POST',
		headers: {'Content-Type': 'application/json'},
		body: JSON.stringify(data),
		credentials: 'include',
	});
}

// End old stuff

function isShowCat (category) {
	if (category.group === 'main' && category.name === 'Anime of the Year') {
		return true;
	} else if (category.group === 'production' && category.entryType !== 'themes' && !category.name.includes('OST') && category.entryType !== 'vas') {
		return true;
	} else if (category.group === 'character' && category.name.includes('Cast')) {
		return true;
	}
	return false;
}

function getPrettyRank (rank) {
	rank--;
	const ranks = ['Winner', '2nd Place', '3rd Place'];
	if (rank < 3) {
		return ranks[rank];
	}
	return `${rank + 1}th Place`;
}

module.exports = {
	makeQuery,
	fuzzyMatch,
	stringMatchesArray,
	shuffle,
	submit,
	isShowCat,
	paginatedQuery,
	userPaginatedQuery,
	slugify,
	getPrettyRank,
};
