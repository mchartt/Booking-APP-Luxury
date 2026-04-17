package com.smartdesk.backend.controller;

import com.smartdesk.backend.dto.*;
import com.smartdesk.backend.model.Booking;
import com.smartdesk.backend.model.Desk;
import com.smartdesk.backend.model.Review;
import com.smartdesk.backend.model.Ticket;
import com.smartdesk.backend.repository.DeskRepository;
import com.smartdesk.backend.service.BookingService;
import com.smartdesk.backend.service.ReviewService;
import com.smartdesk.backend.service.TicketService;
import org.springframework.web.bind.annotation.*;

import java.time.LocalDate;
import java.util.List;

@RestController
@RequestMapping("/api/worker")
public class WorkerController {

    private final TicketService ticketService;
    private final BookingService bookingService;
    private final ReviewService reviewService;
    private final DeskRepository deskRepository;

    public WorkerController(TicketService ticketService, BookingService bookingService, ReviewService reviewService, DeskRepository deskRepository) {
        this.ticketService = ticketService;
        this.bookingService = bookingService;
        this.reviewService = reviewService;
        this.deskRepository = deskRepository;
    }

    @PostMapping("/search")
    public List<DeskDTO> searchDesks(@RequestBody SearchCriteriaDTO criteria) {
        return deskRepository.findAll().stream()
                .filter(d -> criteria.requiredAmenities() == null || d.getAmenities().containsAll(criteria.requiredAmenities()))
                .map(this::toDeskDTO)
                .toList();
    }

    @PostMapping("/{workerId}/book")
    public BookingDTO bookDesk(@PathVariable Long workerId, @RequestBody BookingRequestDTO bookingRequestDTO) {
        Booking booking = bookingService.createBooking(workerId.toString(), bookingRequestDTO.deskID().toString(), bookingRequestDTO.end().toLocalDate());
        return new BookingDTO(booking.getIdBooking(), booking.getDeskId(), booking.getStartTime(), booking.getEndTime());
    }

    @PostMapping("/{workerId}/reviews")
    public void leaveReview(@PathVariable Long workerId, @RequestBody ReviewDTO review) {
        reviewService.leaveReview(workerId, review);
    }

    @PostMapping("/{workerId}/issues")
    public Ticket reportIssue(@PathVariable Long workerId, @RequestBody TicketDTO ticket) {
        return ticketService.openTicket(workerId, ticket.deskID(), ticket.description());
    }

    private DeskDTO toDeskDTO(Desk desk) {
        return new DeskDTO(desk.getDeskId(), desk.getBuilding(), desk.getAmenities());
    }
}
