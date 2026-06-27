import { PersonClient } from '../../../assets/js/clients/PersonClient.js';

describe('PersonClient', function() {
  let client;

  beforeEach(function() {
    client = new PersonClient();
  });

  describe('get()', function() {
    it('should fetch person data successfully', async function() {
      const mockPersonData = {
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
          json: () => Promise.resolve(mockPersonData)
        })
      );

      const result = await client.get(1);

      expect(global.fetch).toHaveBeenCalledWith('/persons/1');
      expect(result).toEqual(mockPersonData);
    });

    it('should throw error when fetch fails', async function() {
      spyOn(global, 'fetch').and.returnValue(
        Promise.resolve({
          ok: false,
          status: 500
        })
      );

      try {
        await client.get(1);
        fail('Expected an error to be thrown');
      } catch (error) {
        expect(error.message).toBe('Failed to fetch person data');
      }

      expect(global.fetch).toHaveBeenCalledWith('/persons/1');
    });

    it('should throw error when fetch rejects', async function() {
      const networkError = new Error('Network error');
      spyOn(global, 'fetch').and.returnValue(
        Promise.reject(networkError)
      );

      try {
        await client.get(1);
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
        await client.get(1);
        fail('Expected an error to be thrown');
      } catch (error) {
        expect(error.message).toBe('Failed to fetch person data');
      }
    });
  });
});
