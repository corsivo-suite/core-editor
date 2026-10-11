import apiFetch from '@wordpress/api-fetch';
import { resolvePages, searchPages } from './api';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

describe( 'configurator page API adapter', () => {
	beforeEach( () => {
		apiFetch.mockReset();
	} );

	it( 'searches the selected language and maps page options', async () => {
		const signal = new AbortController().signal;
		apiFetch.mockResolvedValue( {
			items: [ { id: 17, title: 'Contact' } ],
		} );

		await expect(
			searchPages( 'contact us', { language: 'en', signal } )
		).resolves.toEqual( [ { value: 17, label: 'Contact' } ] );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: 'corsivo/v1/theme-config/pages?search=contact+us&language=en&page=1&per_page=20',
			signal,
		} );
	} );

	it( 'hydrates only valid selected page IDs', async () => {
		apiFetch.mockResolvedValue( {
			items: [
				{ id: 21, title: 'Checkout' },
				{ id: 34, title: 'Account' },
			],
		} );

		await expect(
			resolvePages( [ '21', 0, 'invalid', 34 ], { language: 'it' } )
		).resolves.toEqual( [
			{ value: 21, label: 'Checkout' },
			{ value: 34, label: 'Account' },
		] );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: 'corsivo/v1/theme-config/pages?include=21%2C34&language=it',
			signal: undefined,
		} );
	} );

	it( 'does not call the API without hydratable IDs', async () => {
		await expect( resolvePages( [ '', 0 ] ) ).resolves.toEqual( [] );
		expect( apiFetch ).not.toHaveBeenCalled();
	} );
} );
