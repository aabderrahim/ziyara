import { useEffect, useState } from 'react';
import api from '../api/api';
import TourCard from '../components/TourCard';

function Tours() {
  const [tours, setTours] = useState([]);

  useEffect(() => {
    api.get('/tours')
      .then(res => setTours(res.data))
      .catch(err => console.error(err));
  }, []);

  return (
    <section>
      <h2>Available Tours</h2>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '20px' }}>
        {tours.map(tour => (
          <TourCard key={tour.id} tour={tour} />
        ))}
      </div>
    </section>
  );
}

export default Tours;
