import '../css/app.css';

import { createRoot } from 'react-dom/client';
import { useEffect, useMemo, useState } from 'react';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const emptyState = {
    rooms: [],
    summary: {
        total: 97,
        available: 0,
        booked: 0,
        occupied: 0,
    },
    last_booking: null,
};

function App() {
    const [state, setState] = useState(emptyState);
    const [roomCount, setRoomCount] = useState(4);
    const [isLoading, setIsLoading] = useState(true);
    const [isWorking, setIsWorking] = useState(false);
    const [notice, setNotice] = useState('');

    const roomsByFloor = useMemo(() => {
        return state.rooms.reduce((groupedRooms, room) => {
            groupedRooms[room.floor] = groupedRooms[room.floor] ?? [];
            groupedRooms[room.floor].push(room);
            return groupedRooms;
        }, {});
    }, [state.rooms]);

    useEffect(() => {
        refresh();
    }, []);

    async function request(path, options = {}) {
        const response = await fetch(path, {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers ?? {}),
            },
            ...options,
        });

        const payload = await response.json();

        if (!response.ok) {
            const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : payload.message;
            throw new Error(firstError || 'Request failed.');
        }

        return payload;
    }

    async function refresh() {
        setIsLoading(true);
        setNotice('');

        try {
            setState(await request('/api/rooms'));
        } catch (error) {
            setNotice(error.message);
        } finally {
            setIsLoading(false);
        }
    }

    async function runAction(action) {
        setIsWorking(true);
        setNotice('');

        try {
            setState(await action());
        } catch (error) {
            setNotice(error.message);
        } finally {
            setIsWorking(false);
        }
    }

    function handleBook(event) {
        event.preventDefault();
        runAction(() => request('/api/rooms/book', {
            method: 'POST',
            body: JSON.stringify({ rooms: Number(roomCount) }),
        }));
    }

    return (
        <main className="min-h-screen bg-[#eef3f7] text-[#17212b]">
            <header className="site-header">
                <div>
                    <span className="brand-mark">H</span>
                    <span className="brand-name">Hotel Reservation</span>
                </div>
                <span className="assessment-badge">SDE 3 Assessment</span>
            </header>

            <section className="hero-section">
                <p className="hero-kicker">Unstop recruitment assignment</p>
                <h1>Optimal room booking system</h1>
                <p className="hero-copy">
                    Book up to five rooms with same-floor priority, minimum travel time allocation, random occupancy, and full reset.
                </p>
            </section>

            <section className="app-shell">
                <div className="app-toolbar">
                    <form className="control-bar" onSubmit={handleBook}>
                        <input
                            aria-label="No of Rooms"
                            className="room-count-input"
                            max="5"
                            min="1"
                            placeholder="No of Rooms"
                            type="number"
                            value={roomCount}
                            onChange={(event) => setRoomCount(event.target.value)}
                        />
                        <button className="wire-button primary-action" disabled={isWorking || isLoading} type="submit">
                            Book
                        </button>
                        <button
                            className="wire-button"
                            disabled={isWorking || isLoading}
                            type="button"
                            onClick={() => runAction(() => request('/api/rooms/reset', { method: 'POST' }))}
                        >
                            Reset
                        </button>
                        <button
                            className="wire-button"
                            disabled={isWorking || isLoading}
                            type="button"
                            onClick={() => runAction(() => request('/api/rooms/randomize', { method: 'POST' }))}
                        >
                            Random
                        </button>
                    </form>
                </div>

                <div className="app-meta">
                    <div className="summary-strip">
                        <SummaryItem label="Available" value={state.summary.available} />
                        <SummaryItem label="Booked" value={state.summary.booked} />
                        <SummaryItem label="Occupied" value={state.summary.occupied} />
                    </div>

                    <div className="legend-row">
                        <Legend label="Available" className="legend-available" />
                        <Legend label="Booked" className="legend-booked" />
                        <Legend label="Selected" className="legend-selected" />
                        <Legend label="Occupied" className="legend-occupied" />
                    </div>
                </div>

                {(notice || state.last_booking) && (
                    <div className={notice ? 'status-message' : 'result-panel'}>
                        {notice ? notice : (
                            <>
                                <span className="result-item">
                                    <strong>Booked</strong>
                                    {state.last_booking.rooms.length ? state.last_booking.rooms.join(', ') : 'None'}
                                </span>
                                {/* <span className="result-item">
                                    <strong>Travel</strong>
                                    {state.last_booking.travel_time} min
                                </span>
                                <span className="result-item">
                                    <strong>Rule</strong>
                                    {state.last_booking.strategy}
                                </span> */}
                            </>
                        )}
                    </div>
                )}

                <div className="building-layout">
                    <div className="shaft" aria-label="Staircase and lift">
                        <span>Lift</span>
                        <span>Stairs</span>
                    </div>
                    <div className="floors">
                        {[10, 9, 8, 7, 6, 5, 4, 3, 2, 1].map((floor) => (
                            <FloorRow
                                floor={floor}
                                isLoading={isLoading}
                                key={floor}
                                rooms={roomsByFloor[floor] ?? []}
                            />
                        ))}
                    </div>
                </div>
            </section>
        </main>
    );
}

function SummaryItem({ label, value }) {
    return (
        <div>
            <span>{label}</span>
            <strong>{value}</strong>
        </div>
    );
}

function Legend({ className, label }) {
    return (
        <div className="legend-item">
            <span className={className} />
            {label}
        </div>
    );
}

function FloorRow({ floor, rooms, isLoading }) {
    return (
        <div className="floor-shell">
            <div className="floor-number">F{floor}</div>
            <div className={`floor-row ${floor === 10 ? 'top-floor' : ''}`}>
                {isLoading ? (
                    Array.from({ length: floor === 10 ? 7 : 10 }, (_, index) => (
                        <div className="room room-loading" key={index} />
                    ))
                ) : rooms.map((room) => (
                    <div
                        className={`room room-${room.status} ${room.selected ? 'room-selected' : ''}`}
                        key={room.id}
                        title={`Room ${room.number} - ${room.status}`}
                    >
                        {room.number}
                    </div>
                ))}
            </div>
        </div>
    );
}

createRoot(document.getElementById('root')).render(<App />);
