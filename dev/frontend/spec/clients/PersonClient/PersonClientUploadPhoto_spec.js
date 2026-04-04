import { PersonClient } from '../../../assets/js/clients/PersonClient.js';

describe('PersonClient', function() {
  let client;

  beforeEach(function() {
    client = new PersonClient();
  });

  describe('uploadPhoto()', function() {
    it('should upload a photo successfully', async function() {
      const mockResponse = {
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
          json: () => Promise.resolve(mockResponse)
        })
      );

      const file = new File(['(binary)'], 'photo.jpg', { type: 'image/jpeg' });
      const result = await client.uploadPhoto(1, file);

      expect(global.fetch).toHaveBeenCalledWith(
        '/persons/1/photo',
        jasmine.objectContaining({ method: 'POST' })
      );

      const callArgs = global.fetch.calls.mostRecent().args;
      const body = callArgs[1].body;
      expect(body instanceof FormData).toBe(true);
      expect(body.get('photo')).toBe(file);

      expect(result).toEqual(mockResponse);
    });

    it('should throw error when upload fails', async function() {
      spyOn(global, 'fetch').and.returnValue(
        Promise.resolve({
          ok: false,
          status: 500
        })
      );

      const file = new File(['(binary)'], 'photo.jpg', { type: 'image/jpeg' });

      try {
        await client.uploadPhoto(1, file);
        fail('Expected an error to be thrown');
      } catch (error) {
        expect(error.message).toBe('Failed to upload photo');
      }

      expect(global.fetch).toHaveBeenCalledWith(
        '/persons/1/photo',
        jasmine.objectContaining({ method: 'POST' })
      );
    });

    it('should throw error when fetch rejects', async function() {
      const networkError = new Error('Network error');
      spyOn(global, 'fetch').and.returnValue(
        Promise.reject(networkError)
      );

      const file = new File(['(binary)'], 'photo.jpg', { type: 'image/jpeg' });

      try {
        await client.uploadPhoto(1, file);
        fail('Expected an error to be thrown');
      } catch (error) {
        expect(error).toBe(networkError);
      }
    });

    it('should handle 422 unprocessable entity', async function() {
      spyOn(global, 'fetch').and.returnValue(
        Promise.resolve({
          ok: false,
          status: 422
        })
      );

      const file = new File(['(binary)'], 'photo.jpg', { type: 'image/jpeg' });

      try {
        await client.uploadPhoto(2, file);
        fail('Expected an error to be thrown');
      } catch (error) {
        expect(error.message).toBe('Failed to upload photo');
      }

      expect(global.fetch).toHaveBeenCalledWith(
        '/persons/2/photo',
        jasmine.objectContaining({ method: 'POST' })
      );
    });
  });
});
