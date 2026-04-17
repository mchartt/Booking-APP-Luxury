package com.smartdesk.backend.service;

import com.smartdesk.backend.dto.DeskDTO;
import com.smartdesk.backend.exception.NotFoundException;
import com.smartdesk.backend.model.Desk;
import com.smartdesk.backend.model.Space;
import com.smartdesk.backend.repository.DeskRepository;
import com.smartdesk.backend.repository.HostRepository;
import com.smartdesk.backend.repository.SpaceRepository;
import org.springframework.stereotype.Service;

@Service
public class HostService {

    private final SpaceRepository spaceRepo;
    private final DeskRepository deskRepo;
    private final HostRepository hostRepo;

    public HostService(SpaceRepository spaceRepo, DeskRepository deskRepo, HostRepository hostRepo) {
        this.spaceRepo = spaceRepo;
        this.deskRepo = deskRepo;
        this.hostRepo = hostRepo;
    }

    public Space createSpace(Long hostId, Space space) {
        hostRepo.findById(hostId).orElseThrow(() -> new NotFoundException("Host not found"));
        space.setHostId(hostId);
        return spaceRepo.save(space);
    }

    public void removeSpace(Long hostId, Long spaceId) {
        hostRepo.findById(hostId).orElseThrow(() -> new NotFoundException("Host not found"));
        spaceRepo.deleteById(spaceId);
    }

    public Desk addDesk(Long spaceId, DeskDTO deskDTO) {
        spaceRepo.findById(spaceId).orElseThrow(() -> new NotFoundException("Space not found"));
        Desk desk = new Desk();
        desk.setBuilding(deskDTO.building());
        desk.setAmenities(deskDTO.amenities());
        return deskRepo.save(desk);
    }

    public void removeDesk(Long spaceId, Long deskId) {
        spaceRepo.findById(spaceId).orElseThrow(() -> new NotFoundException("Space not found"));
        deskRepo.deleteById(deskId);
    }

    public Desk editDesk(DeskDTO deskDTO) {
        Desk desk = deskRepo.findById(deskDTO.id()).orElseThrow(() -> new NotFoundException("Desk not found"));
        desk.setBuilding(deskDTO.building());
        desk.setAmenities(deskDTO.amenities());
        return deskRepo.save(desk);
    }
}
