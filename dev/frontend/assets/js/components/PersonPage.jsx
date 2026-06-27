import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { PersonClient } from '../clients/PersonClient';

const PersonPage = () => {
  const { id } = useParams();
  const [person, setPerson] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    (new PersonClient()).get(id)
      .then((data) => {
        setPerson(data);
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message);
        setLoading(false);
      });
  }, [id]);

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <div>
      <h2>{person.first_name} {person.last_name}</h2>
      <img
        src={`/photos/${person.id}.jpg`}
        alt="photo"
        onError={(e) => { e.target.style.display = 'none'; }}
      />
      <p>Birthdate: {person.birthdate}</p>
      <Link to="/persons">Back to list</Link>
      {' '}
      <Link to={`/persons/${id}/edit`} className="btn btn-secondary btn-sm">Edit</Link>
    </div>
  );
};

export default PersonPage;
