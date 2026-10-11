let nextCollectionIdentity = 1;

export function createCollectionIdentity() {
	const identity = `config-item-${ nextCollectionIdentity }`;
	nextCollectionIdentity += 1;
	return identity;
}
