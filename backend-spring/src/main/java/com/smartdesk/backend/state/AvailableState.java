package com.smartdesk.backend.state;

import com.smartdesk.backend.model.Desk;

public class AvailableState implements DeskState {

    @Override
    public void handleState(Desk context) {
        context.setStateName("AVAILABLE");
    }

    public void handleBooking(Desk context) {
        context.changeState(new BookedState());
        context.setStateName("BOOKED");
    }
}
