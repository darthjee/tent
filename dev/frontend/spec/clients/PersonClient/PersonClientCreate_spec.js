import { PersonClient } from '../../../assets/js/clients/PersonClient.js';

describe('PersonClient', function() {
  let client;

  beforeEach(function() {
    client = new PersonClient();
  });

  describe('create()', function() {
    it('should create a person successfully', async function() {
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

      const result = await client.create(mockPersonData);

      expect(global.fetch).toHaveBeenCalledWith(
        '/persons',
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(mockPersonData)
        }
      );
      expect(result).toEqual(mockPersonData);
    });

    it('should throw error when create fails', async function() {
      spyOn(global, 'fetch').and.returnValue(
        Promise.resolve({
          ok: false,
          status: 500
        })
      );

      try {
        await client.create({});
        fail('Expected an error to be thrown');
      } catch (error) {
        expect(error.message).toBe('Failed to create person');
      }

      expect(global.fetch).toHaveBeenCalledWith(
        '/persons',
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({})
        }
      );
    });

    it('should throw error when fetch rejects', async function() {
      const networkError = new Error('Network error');
      spyOn(global, 'fetch').and.returnValue(
        Promise.reject(networkError)
      );

      try {
        await client.create({});
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
        await client.create({});
        fail('Expected an error to be thrown');
      } catch (error) {
        expect(error.message).toBe('Failed to create person');
      }
    });
  });
});
