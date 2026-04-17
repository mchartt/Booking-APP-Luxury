package com.smartdesk.backend.service;

import com.smartdesk.backend.model.Desk;
import com.smartdesk.backend.observer.DeskAvailabilityManager;
import com.smartdesk.backend.observer.WorkQueueSubscriber;
import com.smartdesk.backend.state.AvailableState;
import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.*;

class StateAndObserverTest {

    @Test
    void deskStateTransitionsAndNotifiesWaitingWorker() {
        Desk desk = new Desk();
        desk.setStateName("AVAILABLE");

        DeskAvailabilityManager manager = new DeskAvailabilityManager(desk);
        WorkQueueSubscriber worker = new WorkQueueSubscriber(10L);
        manager.subscribe(worker);

        new AvailableState().handleBooking(desk);
        assertEquals("BOOKED", desk.getStateName());

        manager.releaseDesk();
        assertEquals("AVAILABLE", desk.getStateName());
        assertTrue(worker.isNotified());
    }
}
