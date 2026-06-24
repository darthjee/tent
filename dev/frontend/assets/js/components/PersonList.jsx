import { useEffect, useState } from 'react';
import { PersonClient } from '../clients/PersonClient';
import PhotoUploadForm from './PhotoUploadForm';

const PersonList = () => {
  const [persons, setPersons] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [uploadingForPersonId, setUploadingForPersonId] = useState(null);
  const isUploading = (id) => uploadingForPersonId === id;

  const renderPhotoUpload = (person) => {
    if (isUploading(person.id)) {
      return (
        <PhotoUploadForm
          personId={person.id}
          onSuccess={() => setUploadingForPersonId(null)}
          onCancel={() => setUploadingForPersonId(null)}
        />
      );
    }
    return (
      <button
        className="btn btn-sm btn-link ms-2"
        onClick={() => setUploadingForPersonId(person.id)}
      >
        Upload Photo
      </button>
    );
  };

  useEffect(() => {
    (new PersonClient()).list()
      .then((data) => {
        setPersons(data);
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message);
        setLoading(false);
      });
  }, []);

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <div>
      <h2>Person List</h2>
      <ul>
        {persons.map((person) => (
          <li key={person.id}>
            {person.first_name} {person.last_name} ({person.birthdate})
            {renderPhotoUpload(person)}
          </li>
        ))}
      </ul>
    </div>
  );
};

export default PersonList;
