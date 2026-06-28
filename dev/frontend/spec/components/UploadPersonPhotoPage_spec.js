describe('UploadPersonPhotoPage', function() {
  describe('navigation callbacks', function() {
    it('should navigate to person show page on success', function() {
      const personId = '42';
      const navigate = jasmine.createSpy('navigate');
      const handleSuccess = () => navigate(`/persons/${personId}`);

      handleSuccess();

      expect(navigate).toHaveBeenCalledWith('/persons/42');
    });

    it('should navigate to person show page on cancel', function() {
      const personId = '42';
      const navigate = jasmine.createSpy('navigate');
      const handleCancel = () => navigate(`/persons/${personId}`);

      handleCancel();

      expect(navigate).toHaveBeenCalledWith('/persons/42');
    });

    it('should include the correct person id in the navigation path on success', function() {
      const personId = '99';
      const navigate = jasmine.createSpy('navigate');
      const handleSuccess = () => navigate(`/persons/${personId}`);

      handleSuccess();

      expect(navigate).toHaveBeenCalledWith('/persons/99');
    });

    it('should include the correct person id in the navigation path on cancel', function() {
      const personId = '99';
      const navigate = jasmine.createSpy('navigate');
      const handleCancel = () => navigate(`/persons/${personId}`);

      handleCancel();

      expect(navigate).toHaveBeenCalledWith('/persons/99');
    });
  });

  describe('card header text', function() {
    it('should format the header with first and last name', function() {
      const person = { first_name: 'John', last_name: 'Doe' };
      const header = `Upload Photo for ${person.first_name} ${person.last_name}`;

      expect(header).toBe('Upload Photo for John Doe');
    });

    it('should include both first and last name in the header', function() {
      const person = { first_name: 'Jane', last_name: 'Smith' };
      const header = `Upload Photo for ${person.first_name} ${person.last_name}`;

      expect(header).toContain('Jane');
      expect(header).toContain('Smith');
      expect(header).toContain('Upload Photo for');
    });
  });
});
