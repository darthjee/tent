import { useState } from 'react';
import { PersonClient } from '../clients/PersonClient';

export default function PersonPhotoForm({ personId }) {
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  const handleFileChange = (e) => {
    setFile(e.target.files[0]);
    setError(null);
    setSuccess(false);
  };

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
