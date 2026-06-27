import { PersonClient } from '../../../assets/js/clients/PersonClient.js';

describe('PersonClient', function() {
  let client;

  beforeEach(function() {
    client = new PersonClient();
  });

  describe('update()', function() {
    it('should send a PATCH request and resolve with the updated person', async function() {
      const updatedPerson = {
        id: 1,
        first_name: 'UpdatedFirst',
        last_name: 'UpdatedLast',
        birthdate: '1990-06-15',
        created_at: '2026-01-11 21:59:45',
        updated_at: '2026-06-27 10:00:00',
      };
      const payload = {
        first_name: 'UpdatedFirst',
        last_name: 'UpdatedLast',
        birthdate: '1990-06-15',
      };

      spyOn(global, 'fetch').and.returnValue(
        Promise.resolve({
          ok: true,
          json: () => Promise.resolve(updatedPerson),
        })
      );

      const result = await client.update(1, payload);

      expect(global.fetch).toHaveBeenCalledWith('/persons/1', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      expect(result).toEqual(updatedPerson);
    });

    it('should throw error when response is not ok', async function() {
      spyOn(global, 'fetch').and.returnValue(
        Promise.resolve({
          ok: false,
          status: 422,
        })
      );

      try {
        await client.update(1, { first_name: 'Bad' });
        fail('Expected an error to be thrown');
      } catch (error) {
        expect(error.message).toBe('Failed to update person');
      }

      expect(global.fetch).toHaveBeenCalledWith('/persons/1', jasmine.objectContaining({
        method: 'PATCH',
      }));
    });

    it('should propagate network errors', async function() {
      const networkError = new Error('Network error');
      spyOn(global, 'fetch').and.returnValue(
        Promise.reject(networkError)
      );

      try {
        await client.update(1, { first_name: 'Test' });
        fail('Expected an error to be thrown');
      } catch (error) {
        expect(error).toBe(networkError);
      }
    });
  });
});
