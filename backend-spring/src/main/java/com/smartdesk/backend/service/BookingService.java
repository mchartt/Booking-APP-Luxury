package com.smartdesk.backend.service;

import com.smartdesk.backend.exception.ConflictException;
import com.smartdesk.backend.exception.NotFoundException;
import com.smartdesk.backend.model.Booking;
import com.smartdesk.backend.model.Desk;
import com.smartdesk.backend.observer.DeskAvailabilityManager;
import com.smartdesk.backend.observer.Subscriber;
import com.smartdesk.backend.repository.BookingRepository;
import com.smartdesk.backend.repository.DeskRepository;
import com.smartdesk.backend.state.AvailableState;
import com.smartdesk.backend.state.BookedState;
import org.springframework.stereotype.Service;

import java.time.LocalDate;
import java.time.LocalDateTime;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.locks.ReentrantLock;

@Service
public class BookingService {

    private final BookingRepository bookingRepo;
    private final DeskRepository deskRepo;

    private final Map<String, ReentrantLock> deskLocks = new ConcurrentHashMap<>();
    private final Map<String, Booking> pendingBooking = new ConcurrentHashMap<>();
    private final Map<String, DeskAvailabilityManager> workStations = new ConcurrentHashMap<>();

    public BookingService(BookingRepository bookingRepo, DeskRepository deskRepo) {
        this.bookingRepo = bookingRepo;
        this.deskRepo = deskRepo;
    }

    public Booking createBooking(String workerID, String deskID, LocalDate date) {
        String key = deskID + "_" + date;
        ReentrantLock lock = deskLocks.computeIfAbsent(key, k -> new ReentrantLock());
        lock.lock();
        try {
            Desk desk = deskRepo.findById(Long.valueOf(deskID)).orElseThrow(() -> new NotFoundException("Desk not found"));
            if (!"AVAILABLE".equals(desk.getStateName())) {
                throw new ConflictException("Desk is not available");
            }

            Booking booking = new Booking();
            booking.setWorkerId(Long.valueOf(workerID));
            booking.setDeskId(Long.valueOf(deskID));
            booking.setBookedDay(date);
            booking.setStartTime(LocalDateTime.now());
            booking.setEndTime(date.atTime(18, 0));
            Booking saved = bookingRepo.save(booking);

            new AvailableState().handleBooking(desk);
            deskRepo.save(desk);

            pendingBooking.put(String.valueOf(saved.getIdBooking()), saved);
            workStations.computeIfAbsent(deskID, d -> new DeskAvailabilityManager(desk)).mainBusinessLogic();
            return saved;
        } finally {
            lock.unlock();
        }
    }

    public void removeBooking(Long bookingID) {
        Booking booking = bookingRepo.findById(bookingID).orElseThrow(() -> new NotFoundException("Booking not found"));
        Desk desk = deskRepo.findById(booking.getDeskId()).orElseThrow(() -> new NotFoundException("Desk not found"));
        new BookedState().handleRelease(desk);
        deskRepo.save(desk);

        DeskAvailabilityManager manager = workStations.get(String.valueOf(desk.getDeskId()));
        if (manager != null) {
            manager.releaseDesk();
        }

        pendingBooking.remove(String.valueOf(bookingID));
        bookingRepo.deleteById(bookingID);
    }

    public void notifyMeWhenAvailable(String deskID, String bookedDay, Subscriber subscriber) {
        Desk desk = deskRepo.findById(Long.valueOf(deskID)).orElseThrow(() -> new NotFoundException("Desk not found"));
        DeskAvailabilityManager manager = workStations.computeIfAbsent(deskID, d -> new DeskAvailabilityManager(desk));
        manager.subscribe(subscriber);
    }

    public void createWaitingUser(Subscriber subscriber) {
        notifyMeWhenAvailable("1", LocalDate.now().toString(), subscriber);
    }
}
