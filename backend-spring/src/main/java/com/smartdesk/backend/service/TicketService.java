package com.smartdesk.backend.service;

import com.smartdesk.backend.exception.NotFoundException;
import com.smartdesk.backend.model.Desk;
import com.smartdesk.backend.model.Ticket;
import com.smartdesk.backend.repository.DeskRepository;
import com.smartdesk.backend.repository.TicketRepository;
import com.smartdesk.backend.state.MaintenanceState;
import org.springframework.stereotype.Service;

import java.util.List;

@Service
public class TicketService {

    private final TicketRepository ticketRepo;
    private final DeskRepository deskRepo;

    public TicketService(TicketRepository ticketRepo, DeskRepository deskRepo) {
        this.ticketRepo = ticketRepo;
        this.deskRepo = deskRepo;
    }

    public Ticket openTicket(Long workerId, Long deskId, String description) {
        Desk desk = deskRepo.findById(deskId).orElseThrow(() -> new NotFoundException("Desk not found"));
        Ticket ticket = new Ticket();
        ticket.setDeskId(deskId);
        ticket.setDescription("Worker " + workerId + ": " + description);
        ticket.report(desk);
        return ticketRepo.save(ticket);
    }

    public Desk setDeskMaintenance(Long deskId) {
        Desk desk = deskRepo.findById(deskId).orElseThrow(() -> new NotFoundException("Desk not found"));
        desk.changeState(new MaintenanceState());
        desk.handleState();
        return deskRepo.save(desk);
    }

    public Ticket resolveTicket(Long ticketId, String note) {
        Ticket ticket = ticketRepo.findById(ticketId).orElseThrow(() -> new NotFoundException("Ticket not found"));
        ticket.setStatus("RESOLVED");
        ticket.setTechnicianNote(note);
        return ticketRepo.save(ticket);
    }

    public List<Ticket> getPendingTickets() {
        return ticketRepo.findByStatus("OPEN");
    }
}
