import { useState } from 'react';
import { PersonClient } from '../clients/PersonClient';

/**
 * Form component for uploading a photo for a specific person.
 *
 * Renders a JPEG file input and an "Upload Photo" button. While the upload
 * is in progress the button is disabled and shows "Uploading…". On success
 * it shows a brief confirmation message; on failure it shows the error text.
 *
 * @param {Object}        props          - Component props.
 * @param {number|string} props.personId - The ID of the person whose photo
 *                                         is being uploaded.
 * @returns {JSX.Element} The rendered upload form.
 */
export default function PersonPhotoForm({ personId }) {
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  /**
   * Updates the selected file in state and clears any previous
   * success/error feedback.
   *
   * @param {React.ChangeEvent<HTMLInputElement>} e - The file input change event.
   */
  const handleFileChange = (e) => {
    setFile(e.target.files[0]);
    setError(null);
    setSuccess(false);
  };

  /**
   * Submits the selected file to the server via {@link PersonClient#uploadPhoto}.
   * Manages loading, success, and error states during the async operation.
   *
   * @param {React.FormEvent<HTMLFormElement>} e - The form submit event.
   */
  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!file) return;

    setLoading(true);
    setError(null);
    setSuccess(false);

    try {
      await (new PersonClient()).uploadPhoto(personId, file);
      setSuccess(true);
      setFile(null);
      e.target.reset();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="d-flex align-items-center gap-2 mt-1">
      <input
        type="file"
        accept="image/jpeg"
        className="form-control form-control-sm"
        onChange={handleFileChange}
        required
      />
      <button
        type="submit"
        className="btn btn-sm btn-secondary"
        disabled={loading || !file}
      >
        {loading ? 'Uploading...' : 'Upload Photo'}
      </button>
      {success && <span className="text-success small">Uploaded!</span>}
      {error && <span className="text-danger small">{error}</span>}
    </form>
  );
}
