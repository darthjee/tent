import { PersonClient } from '../../../assets/js/clients/PersonClient.js';

describe('PersonClient', function() {
  let client;

  beforeEach(function() {
    client = new PersonClient();
  });

  describe('list()', function() {
    it('should fetch persons data successfully', async function() {
      const mockPersonsData = {
        id: 1,
        first_name: 'FirstName',
        last_name: 'LastName',
        birthdate: '1985-03-05',
        created_at: '2026-01-11 21:59:45',
        updated_at: '2026-01-11 21:59:45'
      };

      spyOn(global, 'fetch').and.returnValue(
        Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockPersonsData)
        })
      );

      const result = await client.list();

      expect(global.fetch).toHaveBeenCalledWith(
        '/persons'
      );
      expect(result).toEqual(mockPersonsData);
    });

    it('should throw error when fetch fails', async function() {
      spyOn(global, 'fetch').and.returnValue(
        Promise.resolve({
          ok: false,
          status: 500
        })
      );

      try {
        await client.list();
        fail('Expected an error to be thrown');
      } catch (error) {
        expect(error.message).toBe('Failed to fetch persons data');
      }

      expect(global.fetch).toHaveBeenCalledWith(
        '/persons'
      );
    });

    it('should throw error when fetch rejects', async function() {
      const networkError = new Error('Network error');
      spyOn(global, 'fetch').and.returnValue(
        Promise.reject(networkError)
      );

      try {
        await client.list();
        fail('Expected an error to be thrown');
      } catch (error) {
        expect(error).toBe(networkError);
      }
    });

    it('should handle 404 not found', async function() {
      spyOn(global, 'fetch').and.returnValue(
        Promise.resolve({
          ok: false,
          status: 404
        })
      );

      try {
        await client.list();
        fail('Expected an error to be thrown');
      } catch (error) {
        expect(error.message).toBe('Failed to fetch persons data');
      }
    });
  });
});
